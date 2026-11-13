/**
 * Courier delivery dashboard — GPS tracking + delivery management.
 *
 * Features:
 *   - List assigned deliveries
 *   - Browser Geolocation API for real-time GPS
 *   - Updates courier position every 4 seconds
 *   - Status updates: Assigned → Out for Delivery → Delivered
 *   - Map with route from restaurant to customer
 */
(function () {
	'use strict';

	const POLL_INTERVAL = 3000;
	const GPS_INTERVAL = 4000;
	let pollTimer = null;
	let gpsTimer = null;
	let gpsWatchId = null;
	let gpsActive = false;
	let currentDeliveryId = null;

	const elList = document.getElementById('courier-deliveries');
	const elBanner = document.getElementById('courier-gps-banner');
	const elGpsBtn = document.getElementById('gps-toggle');
	if (!elList) return;

	const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
	const base = document.querySelector('meta[name="app-base"]')?.content?.replace(/\/$/, '') || '';

	async function api(endpoint, payload) {
		const formData = new FormData();
		Object.entries(payload).forEach(([k, v]) => formData.append(k, v));
		const res = await fetch(base + '/' + endpoint, {
			method: 'POST', body: formData, credentials: 'same-origin',
			headers: { 'X-CSRF-Token': csrf, 'X-Requested-With': 'fetch', Accept: 'application/json' },
		});
		const json = await res.json();
		if (!json.ok) throw new Error(json.error || 'Request failed');
		return json.data;
	}

	function renderDeliveries(data) {
		const deliveries = data.deliveries || [];
		if (deliveries.length === 0) {
			elList.innerHTML = '<div class="glass" style="padding:40px;text-align:center;border-radius:16px;">'
				+ '<p style="font-size:16px;margin-bottom:4px;">No active deliveries</p>'
				+ '<p style="font-size:13px;opacity:.6;">When a manager assigns you a delivery, it will appear here.</p></div>';
			return;
		}

		elList.innerHTML = deliveries.map(d => {
			const itemsHtml = (d.items || []).map(it => {
				const opts = it.remark ? ' (' + it.remark + ')' : '';
				return '<div class="kitchen-order-item"><span><b>' + it.quantity + '\u00d7</b> ' + it.name + opts + '</span></div>';
			}).join('');

			const nextBtns = (d.next_statuses || []).map(s => {
				const label = s.status === 'Out for Delivery' ? 'Start Delivery' : (s.status === 'Delivered' ? 'Mark Delivered' : s.label);
				const cls = s.status === 'Out for Delivery' ? 'btn-primary' : (s.status === 'Delivered' ? 'btn-primary' : 'btn-glass');
				return '<button class="btn btn-sm ' + cls + '" data-update data-payment="' + d.payment_id + '" data-status="' + s.status + '">' + label + '</button>';
			}).join('');

			return '<div class="courier-delivery-card">'
				+ '<div class="courier-delivery-head">'
				+ '<div><strong>Order #' + d.payment_id + '</strong>'
				+ '<div style="font-size:12px;opacity:.6;margin-top:2px;">PIN: ' + (d.tracking_pin || '') + '</div></div>'
				+ '<span class="track-pill ' + (d.status_class || '') + '"><span class="dot"></span>' + d.status_label + '</span>'
				+ '</div>'
				+ '<div class="courier-route">'
				+ '<div class="courier-route-point"><small>\u{1F3EA} Pickup</small><strong>' + (d.restaurant?.name || 'Caf\u00e9') + '</strong>'
				+ '<small>' + (d.restaurant?.address || '') + '</small></div>'
				+ '<div class="courier-route-arrow">\u2192</div>'
				+ '<div class="courier-route-point"><small>\u{1F3E0} Drop off</small><strong>' + (d.customer?.name || 'Customer') + '</strong>'
				+ '<small>' + (d.customer?.address || '') + '</small>'
				+ '<small>\u{1F4DE} ' + (d.customer?.phone || 'N/A') + '</small></div>'
				+ '</div>'
				+ '<div class="kitchen-order-items">' + itemsHtml + '</div>'
				+ '<div class="kitchen-order-actions">' + nextBtns + '</div>'
				+ '</div>';
		}).join('');
	}

	async function fetchDeliveries() {
		try {
			const data = await api('tracking/api/courier_deliveries.php', { action: 'list' });
			renderDeliveries(data);
		} catch (err) {
			console.error('Courier fetch error:', err);
		}
	}

	// GPS location handling.
	function startGPS() {
		if (!navigator.geolocation) {
			if (elBanner) {
				elBanner.className = 'courier-gps-banner is-error';
				elBanner.textContent = 'Geolocation is not supported by your browser.';
			}
			return;
		}

		if (elBanner) {
			elBanner.className = 'courier-gps-banner';
			elBanner.textContent = 'Requesting location permission...';
		}

		gpsWatchId = navigator.geolocation.watchPosition(
			async (pos) => {
				gpsActive = true;
				if (elBanner) {
					elBanner.className = 'courier-gps-banner is-active';
					elBanner.innerHTML = '\u{1F4CD} GPS active \u00b7 updating every ' + (GPS_INTERVAL / 1000) + 's';
				}
				if (elGpsBtn) elGpsBtn.textContent = 'Stop GPS';

				// Send the position to the server for each active delivery.
				// We send for the first active delivery (or the selected one).
				try {
					const data = await api('tracking/api/courier_deliveries.php', { action: 'list' });
					const active = (data.deliveries || []).filter(d => d.order_status === 'Assigned' || d.order_status === 'Out for Delivery');
					for (const d of active) {
						await api('tracking/api/update_location.php', {
							action: 'update_location',
							payment_id: d.payment_id,
							latitude: pos.coords.latitude.toFixed(7),
							longitude: pos.coords.longitude.toFixed(7),
							accuracy: pos.coords.accuracy ? pos.coords.accuracy.toFixed(1) : '',
						});
					}
				} catch (err) {
					console.error('GPS update error:', err);
				}
			},
			(err) => {
				gpsActive = false;
				if (elBanner) {
					elBanner.className = 'courier-gps-banner is-error';
					const msgs = { 1: 'Location permission denied. Enable it in your browser settings.', 2: 'Location unavailable.', 3: 'Location request timed out.' };
					elBanner.textContent = msgs[err.code] || 'GPS error: ' + err.message;
				}
			},
			{ enableHighAccuracy: true, timeout: 10000, maximumAge: 3000 }
		);
	}

	function stopGPS() {
		if (gpsWatchId !== null) {
			navigator.geolocation.clearWatch(gpsWatchId);
			gpsWatchId = null;
		}
		gpsActive = false;
		if (elBanner) {
			elBanner.className = 'courier-gps-banner';
			elBanner.textContent = 'GPS is off. Turn it on to share your location with customers.';
		}
		if (elGpsBtn) elGpsBtn.textContent = 'Start GPS';
	}

	// GPS toggle button.
	if (elGpsBtn) {
		elGpsBtn.addEventListener('click', () => {
			if (gpsActive) stopGPS();
			else startGPS();
		});
	}

	// Status update buttons.
	elList.addEventListener('click', async (ev) => {
		const btn = ev.target.closest('[data-update]');
		if (!btn) return;
		const paymentId = btn.dataset.payment;
		const status = btn.dataset.status;
		btn.disabled = true;
		btn.textContent = 'Updating...';
		try {
			await api('tracking/api/update_status.php', {
				action: 'update_status',
				payment_id: paymentId,
				new_status: status,
			});
			await fetchDeliveries();
		} catch (err) {
			alert(err.message);
			btn.disabled = false;
		}
	});

	function startPolling() {
		fetchDeliveries();
		pollTimer = setInterval(fetchDeliveries, POLL_INTERVAL);
		document.addEventListener('visibilitychange', () => {
			if (document.hidden) {
				if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
			} else if (!pollTimer) {
				fetchDeliveries();
				pollTimer = setInterval(fetchDeliveries, POLL_INTERVAL);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', startPolling);
	} else {
		startPolling();
	}
})();
