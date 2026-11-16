/* Checkout: delivery address (change without leaving), live order summary, clickable voucher cards,
 * hand-off to Stripe Checkout (or straight to the receipt when a voucher makes the order free). */
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
		total = s.total;
		updatePayState();
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

	/* ---------------- delivery address ---------------- */
	const ADDR_API = 'api/customer/addresses.php';
	const hasStripe = payBtn.dataset.stripe === '1';
	const currentEl = document.getElementById('address-current');
	const optionsEl = document.getElementById('address-options');
	const pickEl = document.getElementById('address-pick');
	const addrForm = document.getElementById('address-form');
	let addresses = JSON.parse(document.getElementById('address-initial').textContent || '[]');
	let total = 0;
	let chosenId = null;

	const clean = (v) => (v && v !== '-' ? v : ''); // old migrated rows used "-" placeholders
	const addrText = (a) => [clean(a.Line1), clean(a.Line2), [clean(a.Postcode), clean(a.City)].filter(Boolean).join(' '), clean(a.State)].filter(Boolean).map(esc).join(', ');

	function renderCurrent() {
		const a = addresses[0];
		document.querySelector('#address-change span').textContent = a ? 'Change' : 'Add address';
		currentEl.innerHTML = a
			? `<p class="address-line">${icon('pin')}<span><strong>${esc(a.Label)} · ${esc(a.Recipient)}</strong><br><span class="muted">${addrText(a)} · ${esc(a.Phone)}</span></span></p>`
			: `<div class="flash">No delivery address yet — add one to place your order.</div>`;
		updatePayState();
	}

	function renderOptions() {
		chosenId = addresses[0] ? String(addresses[0].Address_ID) : null;
		optionsEl.innerHTML = addresses
			.map(
				(a) => `
			<label class="address-option">
				<input type="radio" name="pick" value="${a.Address_ID}" ${String(a.Address_ID) === chosenId ? 'checked' : ''}>
				<span class="address-option-body">
					<strong>${esc(a.Label)} · ${esc(a.Recipient)} ${+a.Is_default ? '<span class="pill pill-amber">Current</span>' : ''}</strong>
					<small>${addrText(a)} · ${esc(a.Phone)}</small>
				</span>
			</label>`
			)
			.join('');
	}

	function showForm(on) {
		pickEl.hidden = on;
		addrForm.hidden = !on;
		if (on) {
			CD.clearErrors(addrForm);
			addrForm.querySelector('[name=Line1]').focus();
		}
	}

	document.getElementById('address-change').addEventListener('click', () => {
		renderOptions();
		showForm(addresses.length === 0); // nothing saved yet: go straight to the form
		CD.modal.open('address-modal');
	});
	optionsEl.addEventListener('change', (ev) => (chosenId = ev.target.value));
	document.getElementById('address-new').addEventListener('click', () => showForm(true));
	document.getElementById('address-back').addEventListener('click', () => (addresses.length ? showForm(false) : CD.modal.close('address-modal')));

	// Pick a saved address: it becomes the default, which is where this order (and its tracking) goes.
	document.getElementById('address-use').addEventListener('click', async (ev) => {
		const btn = ev.currentTarget;
		if (!chosenId) return;
		if (addresses[0] && String(addresses[0].Address_ID) === chosenId) return CD.modal.close('address-modal');
		CD.busy(btn, true);
		try {
			addresses = await api(ADDR_API, { action: 'set_default', Address_ID: chosenId });
			renderCurrent();
			CD.modal.close('address-modal');
			toast(`Delivering to ${addresses[0].Label}`);
		} catch (err) {
			toast(err.message, 'error');
		} finally {
			CD.busy(btn, false);
		}
	});

	// Add a new address: saved as the default and used for this order.
	CD.ajaxForm(addrForm, ADDR_API, {
		onSuccess: (list) => {
			addresses = list;
			renderCurrent();
			CD.modal.close('address-modal');
			addrForm.reset();
			toast('Address saved — delivering there');
		},
	});

	/* ---------------- pay button: label + whether it can be pressed ---------------- */
	function updatePayState() {
		const free = total <= 0;
		const hint = document.getElementById('pay-hint');
		let why = '';
		if (!addresses.length) why = 'Add a delivery address to continue.';
		else if (!free && !hasStripe) why = 'Card payments aren’t set up on this server.';
		payBtn.disabled = !!why;
		hint.hidden = !why;
		hint.textContent = why;
		document.getElementById('pay-label').textContent = free ? 'Place free order' : 'Pay';
		document.getElementById('pay-amount').hidden = free;
	}

	renderCurrent();

	const initial = JSON.parse(document.getElementById('cart-initial').textContent);
	lastSubtotal = initial.subtotal;
	renderSummary(initial);
	loadVouchers();

	/* ---------------- pay: go to Stripe's hosted page (or the receipt, for a free order) ---------------- */
	payBtn.addEventListener('click', async () => {
		const errEl = document.getElementById('pay-error');
		if (errEl) errEl.textContent = '';
		CD.busy(payBtn, true);
		try {
			const { url } = await api('api/shop/checkout.php', { action: 'session' });
			location.href = url; // Stripe Checkout (returns to customer/checkout_return.php), or the receipt when free
		} catch (err) {
			if (errEl) errEl.textContent = err.message;
			toast(err.message, 'error');
			CD.busy(payBtn, false);
		}
	});
})();
