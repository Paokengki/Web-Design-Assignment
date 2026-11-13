/**
 * Kitchen order queue — auto-refreshing list with status update buttons.
 *
 * Polls tracking/api/kitchen_orders.php every 4 seconds.
 * Kitchen staff can advance order status: Accept → Preparing → Ready.
 */
(function () {
	'use strict';

	const POLL_INTERVAL = 4000;
	let pollTimer = null;
	let lastOrders = [];

	const elList = document.getElementById('kitchen-orders');
	const elCount = document.getElementById('kitchen-count');
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

	function timeAgo(iso) {
		const diff = (Date.now() - new Date(iso + 'Z').getTime()) / 1000;
		if (diff < 60) return Math.floor(diff) + 's ago';
		if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
		return Math.floor(diff / 3600) + 'h ago';
	}

	function renderOrders(data) {
		lastOrders = data.orders || [];
		if (elCount) elCount.textContent = lastOrders.length;

		if (lastOrders.length === 0) {
			elList.innerHTML = '<div class="glass" style="padding:40px;text-align:center;border-radius:16px;">'
				+ '<p style="font-size:16px;margin-bottom:4px;">No active orders</p>'
				+ '<p style="font-size:13px;opacity:.6;">New orders will appear here automatically.</p></div>';
			return;
		}

		elList.innerHTML = lastOrders.map(o => {
			const urgent = o.order_status === 'Pending';
			const itemsHtml = o.items.map(it => {
				const opts = [it.sugar ? 'Sugar ' + it.sugar.toLowerCase() : '', it.ice ? 'Ice ' + it.ice.toLowerCase() : '', it.remark ? '"' + it.remark + '"' : ''].filter(Boolean).join(' \u00b7 ');
				return '<div class="kitchen-order-item">'
					+ '<span><b>' + it.quantity + '\u00d7</b> ' + it.name + (opts ? ' <small>(' + opts + ')</small>' : '') + '</span>'
					+ '<span>RM' + (+it.line_total).toFixed(2) + '</span></div>';
			}).join('');

			const actionsHtml = (o.next_statuses || []).map(s => {
				const btnClass = s.status === 'Ready for Pickup' ? 'btn-primary' : 'btn-glass';
				return '<button class="btn btn-sm ' + btnClass + '" data-update data-payment="' + o.payment_id + '" data-status="' + s.status + '">'
					+ s.label + '</button>';
			}).join('');

			return '<div class="kitchen-order-card ' + (urgent ? 'is-urgent' : '') + '">'
				+ '<div class="kitchen-order-head">'
				+ '<div><strong>Order #' + o.payment_id + '</strong>'
				+ '<div class="meta">' + timeAgo(o.created_at) + ' \u00b7 PIN: ' + (o.tracking_pin || '') + '</div></div>'
				+ '<span class="track-pill ' + (o.status_class || '') + '"><span class="dot"></span>' + o.status_label + '</span>'
				+ '</div>'
				+ '<div class="kitchen-order-items">' + itemsHtml + '</div>'
				+ '<div class="kitchen-order-actions">' + actionsHtml + '</div>'
				+ '<div class="kitchen-order-customer">'
				+ '\u{1F4CD} ' + (o.customer?.address || 'No address')
				+ ' \u00b7 \u{1F4DE} ' + (o.customer?.phone || 'N/A')
				+ ' \u00b7 ' + (o.customer?.name || 'Customer')
				+ '</div>'
				+ '</div>';
		}).join('');
	}

	async function fetchOrders() {
		try {
			const data = await api('tracking/api/kitchen_orders.php', { action: 'list' });
			renderOrders(data);
		} catch (err) {
			console.error('Kitchen fetch error:', err);
		}
	}

	// Handle status update button clicks.
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
			await fetchOrders();
		} catch (err) {
			alert(err.message);
			btn.disabled = false;
			btn.textContent = btn.dataset.label || 'Retry';
		}
	});

	function startPolling() {
		fetchOrders();
		pollTimer = setInterval(fetchOrders, POLL_INTERVAL);
		document.addEventListener('visibilitychange', () => {
			if (document.hidden) {
				if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
			} else if (!pollTimer) {
				fetchOrders();
				pollTimer = setInterval(fetchOrders, POLL_INTERVAL);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', startPolling);
	} else {
		startPolling();
	}
})();
