/**
 * Customer tracking map — Leaflet.js + AJAX polling.
 *
 * Features:
 *   - Live map with restaurant, courier, and destination markers
 *   - Road-following routes via OSRM (with straight-line fallback)
 *   - Two-segment route: traveled (restaurant→courier) + remaining (courier→destination)
 *   - Courier trail showing path history
 *   - Permanent marker labels (tooltips)
 *   - Courier position updates via AJAX polling (5s interval)
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

	/**
	 * Update the map with restaurant, destination, and courier positions.
	 */
	async function updateMap(data) {
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
			+ (data.courier.phone ? '<a href="tel:' + data.courier.phone + '" class="btn btn-sm btn-glass">Call</a>' : '')
			+ '</div>';
	}

	/**
	 * Render the ETA.
	 */
	function renderEta(data) {
		if (!elEta) return;
		const est = data.estimated_minutes;
		if (est != null && est > 0) {
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
			await updateMap(lastData);
		} catch (err) {
			console.error('Tracking poll error:', err);
		}
	}

	/**
	 * Start polling.
	 */
	function startPolling() {
		fetchTracking();
		pollTimer = setInterval(fetchTracking, POLL_INTERVAL);

		// Stop polling when the tab is hidden, restart when visible.
		document.addEventListener('visibilitychange', () => {
			if (document.hidden) {
				if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
			} else if (!pollTimer) {
				fetchTracking();
				pollTimer = setInterval(fetchTracking, POLL_INTERVAL);
			}
		});

		// Stop polling when order is terminal.
		setInterval(() => {
			if (lastData && (lastData.order_status === 'Delivered' || lastData.order_status === 'Cancelled')) {
				if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
			}
		}, POLL_INTERVAL);
	}

	// Initialize when DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', startPolling);
	} else {
		startPolling();
	}
})();
