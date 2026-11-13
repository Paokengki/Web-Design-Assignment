/**
 * Manager delivery dashboard — assign couriers, monitor active deliveries.
 *
 * Tabs: Ready for Pickup | Active Deliveries
 * Actions: Assign courier to a ready order
 * Polling: 5s for auto-refresh
 */
(function () {
	'use strict';

	const POLL_INTERVAL = 5000;
	let pollTimer = null;
	let currentTab = 'ready';

	const elReady = document.getElementById('manager-ready');
	const elActive = document.getElementById('manager-active');
	const elReadyList = document.getElementById('ready-list');
	const elActiveList = document.getElementById('active-list');
	if (!elReadyList && !elActiveList) return;

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

	function switchTab(tab) {
		currentTab = tab;
		document.querySelectorAll('.manager-tab').forEach(t => {
			t.classList.toggle('is-active', t.dataset.tab === tab);
		});
		if (elReady) elReady.style.display = tab === 'ready' ? '' : 'none';
		if (elActive) elActive.style.display = tab === 'active' ? '' : 'none';
	}

	function renderReady(data) {
		if (!elReadyList) return;
		const orders = data.orders || [];
		if (orders.length === 0) {
			elReadyList.innerHTML = '<div class="glass" style="padding:40px;text-align:center;border-radius:16px;">'
				+ '<p style="font-size:16px;">No orders waiting for courier</p>'
				+ '<p style="font-size:13px;opacity:.6;">Orders ready for pickup will appear here.</p></div>';
			return;
		}
		elReadyList.innerHTML = orders.map(o => {
			return '<div class="courier-delivery-card">'
				+ '<div class="courier-delivery-head">'
				+ '<div><strong>Order #' + o.payment_id + '</strong>'
				+ '<div style="font-size:12px;opacity:.6;margin-top:2px;">' + (o.restaurant || '') + ' \u00b7 ' + o.item_count + ' items \u00b7 RM' + (+o.amount).toFixed(2) + '</div></div>'
				+ '<span class="track-pill track-ready"><span class="dot"></span>Ready</span>'
				+ '</div>'
				+ '<div style="font-size:13px;opacity:.8;margin:8px 0;">'
				+ '\u{1F4CD} ' + (o.address || 'No address') + '<br>'
				+ '\u{1F464} ' + (o.customer_name || 'Customer') + ' \u00b7 \u{1F4DE} ' + (o.customer_phone || 'N/A')
				+ '</div>'
				+ '<button class="btn btn-sm btn-primary" data-assign data-payment="' + o.payment_id + '">Assign Courier</button>'
				+ '</div>';
		}).join('');
	}

	function renderActive(data) {
		if (!elActiveList) return;
		const deliveries = data.deliveries || [];
		if (deliveries.length === 0) {
			elActiveList.innerHTML = '<div class="glass" style="padding:40px;text-align:center;border-radius:16px;">'
				+ '<p style="font-size:16px;">No active deliveries</p>'
				+ '<p style="font-size:13px;opacity:.6;">Assigned deliveries will appear here.</p></div>';
			return;
		}
		elActiveList.innerHTML = deliveries.map(d => {
			return '<div class="courier-delivery-card">'
				+ '<div class="courier-delivery-head">'
				+ '<div><strong>Order #' + d.payment_id + '</strong>'
				+ '<div style="font-size:12px;opacity:.6;margin-top:2px;">' + (d.restaurant || '') + ' \u00b7 RM' + (+d.amount).toFixed(2) + '</div></div>'
				+ '<span class="track-pill ' + (d.order_status === 'Out for Delivery' ? 'track-delivery' : 'track-assigned') + '"><span class="dot"></span>' + d.status_label + '</span>'
				+ '</div>'
				+ '<div style="font-size:13px;opacity:.8;margin:8px 0;">'
				+ '\u{1F69A} Courier: ' + (d.courier?.name || 'N/A') + (d.courier?.plate_number ? ' (' + d.courier.plate_number + ')' : '') + '<br>'
				+ '\u{1F4CD} ' + (d.address || 'No address') + '<br>'
				+ '\u{1F464} ' + (d.customer_name || 'Customer') + ' \u00b7 \u{1F4DE} ' + (d.customer_phone || 'N/A')
				+ '</div>'
				+ '</div>';
		}).join('');
	}

	async function fetchData() {
		try {
			if (currentTab === 'ready') {
				const data = await api('tracking/api/manager_deliveries.php', { action: 'ready_orders' });
				renderReady(data);
			} else {
				const data = await api('tracking/api/manager_deliveries.php', { action: 'active_deliveries' });
				renderActive(data);
			}
		} catch (err) {
			console.error('Manager fetch error:', err);
		}
	}

	// Tab switching.
	document.querySelectorAll('.manager-tab').forEach(t => {
		t.addEventListener('click', () => { switchTab(t.dataset.tab); fetchData(); });
	});

	// Assign courier modal.
	document.addEventListener('click', async (ev) => {
		const btn = ev.target.closest('[data-assign]');
		if (!btn) return;
		const paymentId = btn.dataset.payment;

		// Fetch available couriers.
		try {
			const data = await api('tracking/api/manager_deliveries.php', { action: 'available_couriers' });
			const couriers = data.couriers || [];
			if (couriers.length === 0) {
				alert('No available couriers. All couriers may be on active deliveries.');
				return;
			}

			// Build and show the modal.
			const backdrop = document.createElement('div');
			backdrop.className = 'assign-modal-backdrop';
			backdrop.innerHTML = '<div class="assign-modal">'
				+ '<h3>Assign Courier to Order #' + paymentId + '</h3>'
				+ '<div class="assign-courier-list">' + couriers.map(c => {
					return '<div class="assign-courier-item" data-courier="' + c.id + '">'
						+ '<div><strong>' + c.name + '</strong>'
						+ '<small>' + (c.phone || '') + (c.plate_number ? ' \u00b7 ' + c.plate_number : '') + '</small></div>'
						+ '<span class="btn btn-sm btn-primary">Assign</span></div>';
				}).join('') + '</div>'
				+ '<button class="btn btn-ghost btn-sm" style="margin-top:12px;width:100%;" data-close-modal>Cancel</button>'
				+ '</div>';
			document.body.appendChild(backdrop);

			// Handle courier selection.
			backdrop.addEventListener('click', async (e) => {
				const item = e.target.closest('[data-courier]');
				if (!item) {
					if (e.target.closest('[data-close-modal]') || e.target === backdrop) backdrop.remove();
					return;
				}
				const courierId = item.dataset.courier;
				backdrop.remove();
				try {
					await api('tracking/api/update_status.php', {
						action: 'update_status',
						payment_id: paymentId,
						new_status: 'Assigned',
						courier_id: courierId,
					});
					fetchData();
				} catch (err) {
					alert(err.message);
				}
			});
		} catch (err) {
			alert(err.message);
		}
	});

	function startPolling() {
		fetchData();
		pollTimer = setInterval(fetchData, POLL_INTERVAL);
		document.addEventListener('visibilitychange', () => {
			if (document.hidden) {
				if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
			} else if (!pollTimer) {
				fetchData();
				pollTimer = setInterval(fetchData, POLL_INTERVAL);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', startPolling);
	} else {
		startPolling();
	}
})();
