/* Checkout: live order summary, clickable voucher cards, hand-off to Stripe Checkout. */
(function () {
	'use strict';
	const { api, esc, icon, toast } = CD;
	const money = (n) => 'RM' + (+n).toFixed(2);
	const linesEl = document.getElementById('summary-lines');
	const totalsEl = document.getElementById('summary-totals');
	const listEl = document.getElementById('voucher-list');
	const countEl = document.getElementById('voucher-count');
	const payBtn = document.getElementById('pay-btn');
	let lastSubtotal = null;
	let busy = false;

	/* ---------------- order summary ---------------- */
	function renderSummary(s) {
		if (!s.items.length) {
			location.href = CD.url('customer/home.php');
			return;
		}
		linesEl.innerHTML = s.items
			.map(
				(it) => `<div class="summary-line"><span><b>${it.quantity}×</b> ${esc(it.itemName)}<small>${esc(it.restaurantName)}${it.sugar ? ` · sugar ${esc(it.sugar.toLowerCase())}, ice ${esc(it.ice.toLowerCase())}` : ''}</small></span><span>${money(it.lineTotal)}</span></div>`
			)
			.join('');
		totalsEl.innerHTML = `
			<div><dt>Subtotal</dt><dd>${money(s.subtotal)}</dd></div>
			${s.promo ? `<div class="discount"><dt>Voucher <span class="pill pill-amber">${esc(s.promo.code)}</span></dt><dd>−${money(s.discount)}</dd></div>` : ''}
			<div><dt>SST 6%</dt><dd>${money(s.sst)}</dd></div>
			<div class="total"><dt>Total</dt><dd>${money(s.total)}</dd></div>`;
		document.getElementById('pay-amount').textContent = money(s.total);
		document.getElementById('earn-points').textContent = Math.floor(s.total);
	}

	/* ---------------- voucher cards ---------------- */
	function endsIn(iso) {
		const days = Math.ceil((new Date(iso) - Date.now()) / 86400000);
		return days <= 1 ? 'Ends today' : `Ends in ${days} days`;
	}

	function voucherCard(v, applied, best) {
		const on = v.code === applied;
		const meta = [v.min_spend > 0 ? `Min. spend ${money(v.min_spend)}` : 'No minimum', endsIn(v.expires), v.left !== null && v.left <= 50 ? `${v.left} left` : ''].filter(Boolean).join(' · ');
		return `
		<button type="button" class="voucher ${on ? 'is-applied' : ''}" role="radio" aria-checked="${on}" data-code="${esc(v.code)}"
			${v.eligible ? '' : 'aria-disabled="true"'} title="${v.eligible ? (on ? 'Click to remove' : 'Click to apply') : `Spend ${money(v.short_by)} more to use this`}">
			<span class="voucher-stub"><b>${+v.percent}%</b><small>OFF</small></span>
			<span class="voucher-body">
				<span class="voucher-top"><strong>${esc(v.title)}</strong>${best && !on ? '<span class="pill pill-ok">Best value</span>' : ''}</span>
				<span class="voucher-code mono">${esc(v.code)}</span>
				<small>${esc(meta)}</small>
				${v.eligible ? `<span class="voucher-save">${on ? `${icon('check')}Applied · you save ${money(v.saves)}` : `Save ${money(v.saves)}`}</span>` : `<span class="voucher-lock">${icon('lock')}Spend ${money(v.short_by)} more</span>`}
			</span>
		</button>`;
	}

	function renderVouchers(d) {
		const usable = d.vouchers.filter((v) => v.eligible);
		const best = usable.reduce((a, v) => (!a || v.saves > a.saves ? v : a), null);
		countEl.textContent = d.vouchers.length ? `${usable.length} of ${d.vouchers.length} usable` : '';
		listEl.innerHTML = d.vouchers.length
			? d.vouchers.map((v) => voucherCard(v, d.applied, best && v.code === best.code)).join('')
			: `<p class="empty voucher-empty">No vouchers available right now.</p>`;
	}

	async function loadVouchers() {
		try {
			renderVouchers(await api('api/shop/cart.php', { action: 'vouchers' }));
		} catch (err) {
			listEl.innerHTML = `<p class="empty voucher-empty">${esc(err.message)}</p>`;
		}
	}

	listEl.addEventListener('click', async (ev) => {
		const card = ev.target.closest('.voucher');
		if (!card || busy) return;
		if (card.getAttribute('aria-disabled') === 'true') {
			if (window.gsap && !CD.reduced) gsap.fromTo(card, { x: 0 }, { keyframes: { x: [-6, 6, -3, 0] }, duration: 0.3 });
			return toast(card.title, 'info');
		}
		busy = true;
		card.classList.add('is-busy');
		try {
			if (card.classList.contains('is-applied')) {
				await CD.cart.removePromo();
				toast('Voucher removed', 'info');
			} else {
				const s = await CD.cart.promo(card.dataset.code);
				toast(`${s.promo.code} applied — you save ${money(s.discount)}`);
			}
		} catch (e) {
			/* toast already shown by the cart helper */
		} finally {
			busy = false;
			loadVouchers();
		}
	});

	// Cart changes (here or in the drawer) can change which vouchers qualify.
	document.addEventListener('cart:updated', (ev) => {
		renderSummary(ev.detail);
		if (ev.detail.subtotal !== lastSubtotal && lastSubtotal !== null && !busy) loadVouchers();
		lastSubtotal = ev.detail.subtotal;
	});

	const initial = JSON.parse(document.getElementById('cart-initial').textContent);
	lastSubtotal = initial.subtotal;
	renderSummary(initial);
	loadVouchers();

	/* ---------------- pay: go to Stripe's hosted page ---------------- */
	payBtn.addEventListener('click', async () => {
		const errEl = document.getElementById('pay-error');
		if (errEl) errEl.textContent = '';
		CD.busy(payBtn, true);
		try {
			const { url } = await api('api/shop/checkout.php', { action: 'session' });
			location.href = url; // Stripe Checkout; it returns to customer/checkout_return.php
		} catch (err) {
			if (errEl) errEl.textContent = err.message;
			toast(err.message, 'error');
			CD.busy(payBtn, false);
		}
	});
})();
