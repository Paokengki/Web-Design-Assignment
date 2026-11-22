/**
 * Customer tracking map — Leaflet.js + AJAX polling.
 *
 * Features:
 *   - Live map with restaurant, courier, and destination markers
 *   - Road-following routes via OSRM (with straight-line fallback)
 *   - Two-segment route: traveled (restaurant→courier) + remaining (courier→destination)
 *   - Courier trail showing path history
 *   - Permanent marker labels (tooltips)
 *   - Courier position updates via AJAX polling (5s interval; 1s in demo mode)
 *   - Demo mode: the full café → customer road route is drawn once and the driver
 *     glides along it (no routing request per position)
 *   - Cancel order (while Pending) / Complete Order (once Delivered) bar
 *   - Status timeline auto-refresh
 *   - ETA countdown
 *   - Falls back to text-only if map fails to load
 *   - Auto-zoom to fit all visible markers
 *
 * Requires: Leaflet.js (loaded from CDN on the tracking page)
 */
(function () {
	'use strict';
	const CD = window.CD || {};
	const money = (n) => 'RM' + (+n).toFixed(2);

	// Get the payment ID from the URL query string.
	const params = new URLSearchParams(window.location.search);
	const paymentId = params.get('id') || params.get('payment_id') || '';
	if (!paymentId) return;

	const POLL_INTERVAL = 5000; // 5 seconds
	const DEMO_POLL_INTERVAL = 1000; // demo lifecycle moves every 5–10 s

	// Route cache: key = "lat1,lng1,lat2,lng2", value = array of [lat,lng] waypoints
	// Prevents re-fetching the same OSRM route on every 5s poll.
	const routeCache = {};
	const ROUTE_CACHE_MAX = 20;

	// Courier trail: array of [lat,lng] positions over time.
	let courierTrail = [];

	let map = null;
	let courierMarker = null;
	let restaurantMarker = null;
	let destMarker = null;
	let routeTraveled = null;   // restaurant → courier (solid, muted color)
	let routeRemaining = null;  // courier → destination (solid, accent color)
	let trailLine = null;       // courier path history
	let lastData = null;
	let pollTimer = null;
	let boundsFitOnce = false;  // only auto-fit bounds on first load

	// DOM elements.
	const elStatus = document.getElementById('track-status');
	const elEta = document.getElementById('track-eta');
	const elTimeline = document.getElementById('track-timeline');
	const elItems = document.getElementById('track-items');
	const elCourier = document.getElementById('track-courier');
	const elMapLoading = document.getElementById('track-map-loading');

	/**
	 * Initialize the Leaflet map.
	 */
	function initMap(lat, lng) {
		if (map) return;
		if (typeof L === 'undefined') {
			console.warn('Leaflet not loaded — map disabled');
			if (elMapLoading) elMapLoading.textContent = 'Map unavailable';
			return;
		}
		map = L.map('track-map', {
			zoomControl: true,
			attributionControl: true,
		}).setView([lat || 3.0733, lng || 101.5830], 14);

		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: '&copy; OpenStreetMap contributors',
			maxZoom: 19,
		}).addTo(map);

		if (elMapLoading) elMapLoading.style.display = 'none';
	}

	/**
	 * Create or update a marker with a permanent tooltip label.
	 */
	function updateMarker(ref, lat, lng, badge, tooltipText, iconClass, color) {
		if (!map || lat == null || lng == null) return;
		const latlng = [lat, lng];

		if (!ref) {
			const icon = L.divIcon({
				className: 'track-marker ' + (iconClass || ''),
				html: '<div style="background:' + (color || '#3b82f6') + ';width:30px;height:30px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;">' + (badge || '') + '</div>',
				iconSize: [30, 30],
				iconAnchor: [15, 15],
			});
			ref = L.marker(latlng, { icon, zIndexOffset: 500 }).addTo(map);
			if (tooltipText) {
				ref.bindTooltip(tooltipText, {
					permanent: true,
					direction: 'top',
					offset: [0, -12],
					className: 'track-marker-label',
				});
			}
		} else {
			ref.setLatLng(latlng);
		}
		return ref;
	}

	/**
	 * Fetch a road-following route from OSRM.
	 * Returns an array of [lat,lng] waypoints, or null if the request fails.
	 * Falls back silently to a straight line (caller handles null).
	 *
	 * Uses the public OSRM demo server with driving profile.
	 * Rate-limited by the 5-second poll interval and route cache.
	 */
	async function fetchRoadRoute(fromLat, fromLng, toLat, toLng) {
		const key = fromLat.toFixed(5) + ',' + fromLng.toFixed(5) + ',' + toLat.toFixed(5) + ',' + toLng.toFixed(5);
		if (routeCache[key]) return routeCache[key];

		// OSRM expects lng,lat order (not lat,lng).
		const url = 'https://router.project-osrm.org/route/v1/driving/' +
			fromLng + ',' + fromLat + ';' + toLng + ',' + toLat +
			'?overview=full&geometries=geojson';

		try {
			const res = await fetch(url, { signal: AbortSignal.timeout(4000) });
			if (!res.ok) return null;
			const json = await res.json();
			if (json.code !== 'Ok' || !json.routes || !json.routes[0]) return null;

			// OSRM GeoJSON coordinates are [lng,lat] — convert to [lat,lng] for Leaflet.
			const coords = json.routes[0].geometry.coordinates.map(c => [c[1], c[0]]);

			// Cache the result (simple FIFO eviction).
			const keys = Object.keys(routeCache);
			if (keys.length >= ROUTE_CACHE_MAX) delete routeCache[keys[0]];
			routeCache[key] = coords;

			return coords;
		} catch (err) {
			// Network error, timeout, or JSON parse failure — fall back to straight line.
			return null;
		}
	}

	/**
	 * Draw or update a route polyline with road-following coordinates if available.
	 * Falls back to a straight line if OSRM fails.
	 *
	 * @param {L.Polyline|null} lineRef - existing polyline or null
	 * @param {number} fromLat
	 * @param {number} fromLng
	 * @param {number} toLat
	 * @param {number} toLng
	 * @param {object} style - {color, weight, opacity, dashArray?}
	 * @returns {L.Polyline} the updated or newly created polyline
	 */
	async function drawRouteSegment(lineRef, fromLat, fromLng, toLat, toLng, style) {
		if (!map || fromLat == null || toLat == null) return lineRef;

		// Try to get road-following coordinates; fall back to straight line.
		const roadCoords = await fetchRoadRoute(fromLat, fromLng, toLat, toLng);
		const latlngs = roadCoords || [[fromLat, fromLng], [toLat, toLng]];

		if (lineRef) {
			lineRef.setLatLngs(latlngs);
		} else {
			lineRef = L.polyline(latlngs, {
				color: style.color || '#3b82f6',
				weight: style.weight || 5,
				opacity: style.opacity || 0.85,
				dashArray: style.dashArray || null,
				lineCap: 'round',
				lineJoin: 'round',
			}).addTo(map);
		}
		return lineRef;
	}

	/**
	 * Clear a polyline from the map if it exists.
	 */
	function clearLine(ref) {
		if (ref) {
			map.removeLayer(ref);
		}
		return null;
	}

	/* ── Demo mode map ─────────────────────────────────────────
	 * One road route café → customer, fetched once. The driver's place on it comes from
	 * demo.progress (0–1) and is animated smoothly between polls.
	 */
	let demoRoute = null;     // [[lat,lng], ...]
	let demoCum = null;       // cumulative distance at each route point
	let demoDoneLine = null;  // part of the route already driven
	let demoState = null;
	let demoFrameId = null;

	function pointAlongRoute(p) {
		const total = demoCum[demoCum.length - 1];
		const target = Math.max(0, Math.min(1, p)) * total;
		let i = 0;
		while (i < demoCum.length - 2 && demoCum[i + 1] < target) i++;
		const seg = demoCum[i + 1] - demoCum[i] || 1;
		const f = Math.max(0, Math.min(1, (target - demoCum[i]) / seg));
		const a = demoRoute[i], b = demoRoute[i + 1] || a;
		return { idx: i, pos: [a[0] + (b[0] - a[0]) * f, a[1] + (b[1] - a[1]) * f] };
	}

	function demoFrame(now) {
		demoFrameId = null;
		if (!demoState || !demoRoute || !map) return;
		let p = demoState.base;
		const moving = demoState.status === 'Out for Delivery';
		if (moving) p = Math.min(1, demoState.base + (now - demoState.at) / 1000 / demoState.len);
		const { idx, pos } = pointAlongRoute(p);
		courierMarker = updateMarker(courierMarker, pos[0], pos[1], 'C', demoState.name, 'courier-marker', '#f97316');
		if (demoDoneLine) demoDoneLine.setLatLngs(demoRoute.slice(0, idx + 1).concat([pos]));
		if (moving && p < 1) demoFrameId = requestAnimationFrame(demoFrame);
	}

	async function updateMapDemo(data) {
		const r = data.restaurant || {}, d = data.destination || {};
		const rl = r.latitude, rg = r.longitude, dl = d.latitude, dg = d.longitude;
		if (!map) initMap(rl ?? dl ?? 3.0733, rg ?? dg ?? 101.5830);
		if (!map) return;
		if (rl != null) restaurantMarker = updateMarker(restaurantMarker, rl, rg, 'R', r.name || 'Restaurant', 'rest-marker', '#14b8a6');
		if (dl != null) destMarker = updateMarker(destMarker, dl, dg, 'D', 'Your location', 'dest-marker', '#ef4444');
		if (rl == null || dl == null) return;

		if (!demoRoute) {
			demoRoute = (await fetchRoadRoute(rl, rg, dl, dg)) || [[rl, rg], [dl, dg]];
			const k = Math.cos(rl * Math.PI / 180);
			demoCum = [0];
			for (let i = 1; i < demoRoute.length; i++) {
				const dy = demoRoute[i][0] - demoRoute[i - 1][0], dx = (demoRoute[i][1] - demoRoute[i - 1][1]) * k;
				demoCum.push(demoCum[i - 1] + Math.hypot(dx, dy));
			}
			const line = L.polyline(demoRoute, { color: '#3b82f6', weight: 5, opacity: 0.8, lineCap: 'round', lineJoin: 'round' }).addTo(map);
			demoDoneLine = L.polyline([], { color: '#14b8a6', weight: 6, opacity: 0.95, lineCap: 'round', lineJoin: 'round' }).addTo(map);
			map.fitBounds(line.getBounds(), { padding: [50, 50], maxZoom: 16 });
			boundsFitOnce = true;
		}

		const s = data.order_status;
		const onRoad = data.courier && (s === 'Assigned' || s === 'Out for Delivery' || s === 'Delivered');
		if (!onRoad) {
			demoState = null;
			if (courierMarker) { map.removeLayer(courierMarker); courierMarker = null; }
			return;
		}
		demoState = {
			status: s,
			base: s === 'Delivered' ? 1 : (s === 'Out for Delivery' ? (data.demo.progress || 0) : 0),
			len: data.demo.length || 5,
			at: performance.now(),
			name: data.courier.name || 'Driver',
		};
		if (!demoFrameId) demoFrameId = requestAnimationFrame(demoFrame);
	}

	/**
	 * Update the map with restaurant, destination, and courier positions.
	 */
	async function updateMap(data) {
		if (data.demo && data.demo.enabled) return updateMapDemo(data);
		const restLat = data.restaurant?.latitude;
		const restLng = data.restaurant?.longitude;
		const destLat = data.destination?.latitude;
		const destLng = data.destination?.longitude;
		const courierLat = data.courier?.latitude;
		const courierLng = data.courier?.longitude;

		// Initialize map with the first available coordinate.
		const initLat = restLat || destLat || courierLat || 3.0733;
		const initLng = restLng || destLng || courierLng || 101.5830;
		if (!map) initMap(initLat, initLng);
		if (!map) return;

		// Restaurant marker (origin) — teal, badge "R".
		if (restLat != null && restLng != null) {
			restaurantMarker = updateMarker(restaurantMarker, restLat, restLng, 'R',
				data.restaurant?.name || 'Restaurant', 'rest-marker', '#14b8a6');
		}

		// Destination marker (customer) — red, badge "D".
		if (destLat != null && destLng != null) {
			destMarker = updateMarker(destMarker, destLat, destLng, 'D',
				'Your Location', 'dest-marker', '#ef4444');
		}

		// Courier marker (moving) — orange, badge "C".
		if (courierLat != null && courierLng != null) {
			courierMarker = updateMarker(courierMarker, courierLat, courierLng, 'C',
				data.courier?.name || 'Courier', 'courier-marker', '#f97316');

			// Add to trail (avoid duplicates at same position).
			const last = courierTrail[courierTrail.length - 1];
			if (!last || last[0] !== courierLat || last[1] !== courierLng) {
				courierTrail.push([courierLat, courierLng]);
				// Limit trail to 100 points to avoid memory growth.
				if (courierTrail.length > 100) courierTrail.shift();

				// Draw/update trail line (faded orange dotted).
				if (courierTrail.length >= 2) {
					if (trailLine) {
						trailLine.setLatLngs(courierTrail);
					} else {
						trailLine = L.polyline(courierTrail, {
							color: '#f97316',
							weight: 3,
							opacity: 0.4,
							dashArray: '4,6',
						}).addTo(map);
					}
				}
			}
		}

		// ── Route drawing logic ──────────────────────────────────
		// Case 1: Courier is active → draw two segments:
		//   - Traveled: restaurant → courier (solid, teal/muted)
		//   - Remaining: courier → destination (solid, orange/accent)
		// Case 2: No courier yet → draw single route: restaurant → destination (solid, blue)

		if (courierLat != null && courierLng != null) {
			// Two-segment route.
			if (restLat != null && restLng != null) {
				routeTraveled = await drawRouteSegment(routeTraveled,
					restLat, restLng, courierLat, courierLng,
					{ color: '#14b8a6', weight: 5, opacity: 0.7 });
			}
			if (destLat != null && destLng != null) {
				routeRemaining = await drawRouteSegment(routeRemaining,
					courierLat, courierLng, destLat, destLng,
					{ color: '#f97316', weight: 5, opacity: 0.85 });
			}
		} else {
			// No courier — single route from restaurant to destination.
			// Clear any stale two-segment routes from previous courier data.
			routeTraveled = clearLine(routeTraveled);
			routeRemaining = clearLine(routeRemaining);

			if (restLat != null && destLat != null) {
				routeRemaining = await drawRouteSegment(routeRemaining,
					restLat, restLng, destLat, destLng,
					{ color: '#3b82f6', weight: 5, opacity: 0.85 });
			}
		}

		// ── Auto-zoom: fit bounds to show all markers ────────────
		// Only auto-fit on first load or when markers first appear.
		// Subsequent updates let the user pan/zoom freely.
		if (map) {
			const bounds = [];
			if (restLat != null && restLng != null) bounds.push([restLat, restLng]);
			if (destLat != null && destLng != null) bounds.push([destLat, destLng]);
			if (courierLat != null && courierLng != null) bounds.push([courierLat, courierLng]);

			if (bounds.length >= 2 && !boundsFitOnce) {
				map.fitBounds(bounds, { padding: [50, 50], maxZoom: 16 });
				boundsFitOnce = true;
			} else if (bounds.length === 1 && !boundsFitOnce) {
				map.setView(bounds[0], 15);
				boundsFitOnce = true;
			}
		}
	}

	/**
	 * Render the status timeline.
	 */
	function renderTimeline(data) {
		if (!elTimeline) return;
		const flow = ['Pending', 'Accepted', 'Preparing', 'Ready for Pickup', 'Assigned', 'Out for Delivery', 'Delivered'];
		const currentIdx = flow.indexOf(data.order_status);
		const logMap = {};
		(data.timeline || []).forEach(t => { logMap[t.status] = t; });

		const icons = {
			'Pending': '\u{1F4CB}', 'Accepted': '\u2705', 'Preparing': '\u{1F373}',
			'Ready for Pickup': '\u{1F4E6}', 'Assigned': '\u{1F69A}',
			'Out for Delivery': '\u{1F6B2}', 'Delivered': '\u{1F3E0}',
		};

		let html = '<h3>Order Timeline</h3><ul class="track-timeline-list">';
		flow.forEach((status, idx) => {
			const log = logMap[status];
			const isDone = idx < currentIdx || (idx === currentIdx && data.order_status === 'Delivered');
			const isCurrent = idx === currentIdx && data.order_status !== 'Delivered';
			const cls = isDone ? 'is-done' : (isCurrent ? 'is-current' : '');
			const time = log ? new Date(log.time + 'Z').toLocaleTimeString('en-MY', { hour: '2-digit', minute: '2-digit' }) : '';

			html += '<li class="track-timeline-item ' + cls + '">'
				+ '<div class="track-timeline-dot">' + (icons[status] || '\u25CF') + '</div>'
				+ '<div class="track-timeline-body">'
				+ '<strong>' + (log?.label || status) + '</strong>'
				+ (time ? '<small>' + time + '</small>' : '<small>Pending</small>')
				+ (log?.note ? '<div class="track-note">' + log.note + '</div>' : '')
				+ '</div></li>';
		});
		html += '</ul>';
		elTimeline.innerHTML = html;
	}

	/**
	 * Render order items.
	 */
	function renderItems(data) {
		if (!elItems) return;
		if (!data.items || data.items.length === 0) {
			elItems.style.display = 'none';
			return;
		}
		let html = '<h3>Order Items</h3>';
		data.items.forEach(it => {
			const opts = [it.sugar ? 'Sugar ' + it.sugar.toLowerCase() : '', it.ice ? 'Ice ' + it.ice.toLowerCase() : '', it.remark ? '"' + it.remark + '"' : ''].filter(Boolean).join(' \u00b7 ');
			html += '<div class="track-item-row">'
				+ '<div class="name"><b>' + it.quantity + '\u00d7</b> ' + it.name
				+ (opts ? '<small>' + opts + '</small>' : '')
				+ '</div>'
				+ '<div class="price">' + money(it.line_total) + '</div>'
				+ '</div>';
		});
		elItems.innerHTML = html;
	}

	/**
	 * Render courier info.
	 */
	function renderCourier(data) {
		if (!elCourier) return;
		if (!data.courier) {
			elCourier.style.display = 'none';
			return;
		}
		elCourier.style.display = 'flex';
		const initials = (data.courier.name || '?').split(' ').map(s => s[0]).join('').slice(0, 2).toUpperCase();
		elCourier.innerHTML = ''
			+ '<div class="track-courier-avatar">' + initials + '</div>'
			+ '<div class="track-courier-info">'
			+ '<strong>' + (data.courier.name || 'Courier') + '</strong>'
			+ '<small>' + (data.courier.phone || '') + (data.courier.plate_number ? ' \u00b7 ' + data.courier.plate_number : '') + '</small>'
			+ '</div>'
			+ '<div class="track-courier-actions">'
			+ '<button type="button" class="btn btn-sm btn-glass" data-chat-open="driver">Message</button>'
			+ (data.courier.phone ? '<a href="tel:' + data.courier.phone + '" class="btn btn-sm btn-glass">Call</a>' : '')
			+ '</div>';
	}

	/**
	 * Render the ETA.
	 */
	const DEMO_STEP_TEXT = {
		'Pending': 'Waiting for the café to accept',
		'Accepted': 'Café accepted — kitchen starting',
		'Preparing': 'Your food is being prepared',
		'Ready for Pickup': 'Ready — finding a driver',
		'Assigned': 'Driver heading to the café',
		'Out for Delivery': 'Driver on the way to you',
	};

	function renderEta(data) {
		if (!elEta) return;
		const est = data.estimated_minutes;
		const demo = data.demo;
		if (demo && demo.enabled && DEMO_STEP_TEXT[data.order_status]) {
			const left = demo.remaining != null ? Math.ceil(demo.remaining) : null;
			elEta.innerHTML = '<strong>' + (left != null ? left + 's' : '\u2026') + '</strong><div><small>' + DEMO_STEP_TEXT[data.order_status] + '</small><br><small>Demo timing</small></div>';
		} else if (est != null && est > 0) {
			elEta.innerHTML = '<strong>' + est + '</strong><div><small>min estimated</small><br><small>remaining</small></div>';
		} else if (data.order_status === 'Delivered') {
			elEta.innerHTML = '<strong>\u2713</strong><div><small>Delivered</small></div>';
		} else if (data.order_status === 'Cancelled') {
			elEta.innerHTML = '<strong>\u2717</strong><div><small>Cancelled</small></div>';
		} else {
			elEta.innerHTML = '<strong>\u2014</strong><div><small>Calculating</small></div>';
		}
	}

	/**
	 * Render the status pill.
	 */
	function renderStatus(data) {
		if (!elStatus) return;
		elStatus.innerHTML = '<span class="track-pill ' + (data.order_status_class || '') + '">'
			+ '<span class="dot"></span>'
			+ (data.order_status_label || data.order_status)
			+ '</span>';
	}

	/**
	 * Cancel (while the café has not accepted yet) / Complete Order (once delivered).
	 */
	const elActions = document.getElementById('track-actions');
	let actionsHtml = '';
	let actionBusy = false;
	const esc = (v) => (CD.esc ? CD.esc(v) : String(v ?? ''));

	function renderActions(data) {
		if (!elActions) return;
		const pts = data.points || 0;
		const ptsText = pts + ' point' + (pts === 1 ? '' : 's');
		let tone = '', title = '', sub = '', button = '';

		if (data.order_status === 'Cancelled') {
			tone = 'is-bad';
			title = 'Order cancelled';
			sub = data.refund && data.refund.amount > 0
				? money(data.refund.amount) + ' refunded to your card — it usually shows in 5–10 working days.'
				: (data.provider === 'VOUCHER' ? 'Your voucher is back in your account.' : 'Nothing was charged.');
		} else if (data.can_cancel) {
			const left = data.demo && data.demo.enabled && data.demo.remaining != null ? Math.ceil(data.demo.remaining) : null;
			title = 'Changed your mind?';
			sub = left != null
				? 'You can cancel for ' + left + ' more second' + (left === 1 ? '' : 's') + ', until the café accepts.' + (data.amount > 0 ? ' You get a full refund.' : '')
				: 'You can cancel until the café accepts your order.';
			button = '<button type="button" class="btn btn-danger" data-act="cancel">Cancel order' + (left != null ? ' (' + left + 's)' : '') + '</button>';
		} else if (data.completed) {
			tone = 'is-ok';
			title = 'Order completed';
			sub = data.points_rule === 'on_complete' && pts > 0 ? '+' + ptsText + ' added to your balance. Thanks for ordering!' : 'Thanks for ordering with CafeDash!';
		} else if (data.can_complete) {
			tone = 'is-ok';
			title = 'Your order has arrived';
			sub = pts > 0 ? 'Tap Complete Order to collect +' + ptsText + '.' : 'Tap Complete Order to finish.';
			button = '<button type="button" class="btn btn-primary" data-act="complete">Complete order' + (pts > 0 ? ' · +' + pts + ' pts' : '') + '</button>';
		} else {
			title = 'Complete Order unlocks once delivered';
			sub = pts > 0 ? 'Confirm you have your food to earn ' + ptsText + '.' : 'Confirm you have your food to finish the order.';
			button = '<button type="button" class="btn btn-primary" disabled title="Available once delivered">Complete order</button>';
		}

		const html = '<div class="track-actions-text ' + tone + '"><strong>' + esc(title) + '</strong><small>' + esc(sub) + '</small></div>' + button;
		if (html === actionsHtml) return; // keep focus / hover state
		actionsHtml = html;
		elActions.innerHTML = html;
	}

	if (elActions) {
		elActions.addEventListener('click', async (ev) => {
			const btn = ev.target.closest('[data-act]');
			if (!btn || btn.disabled || actionBusy || !lastData) return;
			const act = btn.dataset.act;
			if (act === 'cancel') {
				const ok = await CD.confirm({
					title: 'Cancel this order?',
					body: lastData.amount > 0 ? money(lastData.amount) + ' will be refunded to your card.' : 'Your voucher will be returned to your account.',
					confirmText: 'Yes, cancel order',
					cancelText: 'Keep order',
					danger: true,
				});
				if (!ok) return;
			}
			actionBusy = true;
			btn.disabled = true;
			try {
				const res = await CD.api('tracking/api/order_actions.php', { action: act, payment_id: paymentId });
				CD.toast(res.message || 'Done.', 'success');
			} catch (err) {
				CD.toast(err.message, 'error');
			} finally {
				actionBusy = false;
				actionsHtml = '';
				await fetchTracking();
				schedulePoll();
			}
		});
	}

	/**
	 * Fetch tracking data from the API.
	 */
	async function fetchTracking() {
		try {
			const formData = new FormData();
			formData.append('action', 'get');
			formData.append('payment_id', paymentId);

			const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
			const base = document.querySelector('meta[name="app-base"]')?.content?.replace(/\/$/, '') || '';
			const res = await fetch(base + '/tracking/api/order_status.php', {
				method: 'POST',
				body: formData,
				credentials: 'same-origin',
				headers: { 'X-CSRF-Token': csrf, 'X-Requested-With': 'fetch', Accept: 'application/json' },
			});
			const json = await res.json();
			if (!json.ok) {
				console.error('Tracking API error:', json.error);
				return;
			}
			lastData = json.data;
			renderStatus(lastData);
			renderEta(lastData);
			renderTimeline(lastData);
			renderItems(lastData);
			renderCourier(lastData);
			renderActions(lastData);
			document.dispatchEvent(new CustomEvent('cd:tracking', { detail: lastData }));
			await updateMap(lastData);
		} catch (err) {
			console.error('Tracking poll error:', err);
		}
	}

	/**
	 * Polling: a setTimeout loop (never overlaps), 1 s in demo mode, paused while the tab is hidden,
	 * stopped once the order is Delivered or Cancelled.
	 */
	const isTerminal = () => lastData && (lastData.order_status === 'Delivered' || lastData.order_status === 'Cancelled');

	function schedulePoll() {
		if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; }
		if (document.hidden || isTerminal()) return;
		const ms = lastData && lastData.demo && lastData.demo.enabled ? DEMO_POLL_INTERVAL : POLL_INTERVAL;
		pollTimer = setTimeout(async () => {
			pollTimer = null;
			await fetchTracking();
			schedulePoll();
		}, ms);
	}

	async function startPolling() {
		await fetchTracking();
		schedulePoll();
		document.addEventListener('visibilitychange', async () => {
			if (document.hidden) {
				if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; }
			} else if (!pollTimer && !isTerminal()) {
				await fetchTracking();
				schedulePoll();
			}
		});
	}

	// Initialize when DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', startPolling);
	} else {
		startPolling();
	}
})();
