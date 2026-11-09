/* CafeDash core/cart.js — slide-out cart on every customer page.
 * CD.cart.add({food_id, quantity, sugar, ice, remark}), CD.cart.open(), CD.cart.refresh()
 * Fires `cart:updated` on document with the priced summary (checkout listens to it). */
(function () {
	'use strict';
	const { api, esc, icon, toast } = CD;
	const API = 'api/shop/cart.php';
	const drawer = document.getElementById('cart-drawer');
	if (!drawer) return;

	const panel = drawer.querySelector('.drawer-panel');
	const backdrop = drawer.querySelector('.drawer-backdrop');
	const list = document.getElementById('cart-items');
	const foot = document.getElementById('cart-foot');
	const hasGsap = !!window.gsap && !CD.reduced;
	const money = (n) => 'RM' + (+n).toFixed(2);
	let summary = null;
	let lastFocus = null;

	function badge(count) {
		document.querySelectorAll('[data-cart-count]').forEach((el) => {
			el.hidden = !count;
			if (el.textContent !== String(count) && count && hasGsap) gsap.fromTo(el, { scale: 1.6 }, { scale: 1, duration: 0.5, ease: 'back.out(3)' });
			el.textContent = count;
		});
	}

	function options(it) {
		const o = [];
		if (it.sugar) o.push(`Sugar ${it.sugar.toLowerCase()}`);
		if (it.ice) o.push(`Ice ${it.ice.toLowerCase()}`);
		if (it.remark) o.push(`“${esc(it.remark)}”`);
		return o.join(' · ');
	}

	function render(s) {
		summary = s;
		badge(s.count);
		if (!s.items.length) {
			list.innerHTML = `<div class="empty">${icon('bag')}<p>Your cart is empty.</p><a class="btn btn-glass btn-sm" href="${CD.url('customer/home.php')}">Browse cafés</a></div>`;
			foot.hidden = true;
		} else {
			list.innerHTML = s.items
				.map(
					(it) => `
				<div class="line" data-id="${esc(it.cartId)}">
					<div class="line-img"><img src="${esc(it.image)}" alt="" loading="lazy" onerror="this.remove()"></div>
					<div>
						<h4>${esc(it.itemName)}</h4>
						<small>${esc(it.restaurantName)}</small>
						${options(it) ? `<small>${options(it)}</small>` : ''}
					</div>
					<div class="line-side">
						<span class="line-price">${money(it.lineTotal)}</span>
						<div class="stepper" role="group" aria-label="Quantity of ${esc(it.itemName)}">
							<button type="button" data-step="-1" aria-label="One less">${icon(it.quantity === 1 ? 'trash' : 'minus')}</button>
							<output>${it.quantity}</output>
							<button type="button" data-step="1" aria-label="One more">${icon('plus')}</button>
						</div>
					</div>
				</div>`
				)
				.join('');
			foot.hidden = false;
			foot.querySelector('[data-cart-subtotal]').textContent = money(s.subtotal);
			foot.querySelector('[data-cart-sst]').textContent = money(s.sst);
			foot.querySelector('[data-cart-total]').textContent = money(s.total);
			const dRow = foot.querySelector('[data-cart-discount-row]');
			dRow.hidden = !s.promo;
			if (s.promo) {
				foot.querySelector('[data-cart-promo]').textContent = s.promo.code;
				foot.querySelector('[data-cart-discount]').textContent = '−' + money(s.discount);
			}
		}
		if (s.promo_error) toast(s.promo_error, 'info');
		document.dispatchEvent(new CustomEvent('cart:updated', { detail: s }));
	}

	async function call(payload) {
		try {
			const s = await api(API, payload);
			render(s);
			return s;
		} catch (err) {
			toast(err.message, 'error');
			throw err;
		}
	}

	function open() {
		lastFocus = document.activeElement;
		drawer.classList.add('is-open');
		drawer.setAttribute('aria-hidden', 'false');
		document.body.style.overflow = 'hidden';
		if (hasGsap) {
			gsap.to(backdrop, { opacity: 1, duration: 0.3 });
			gsap.fromTo(panel, { xPercent: 110 }, { xPercent: 0, duration: 0.6, ease: 'expo.out' });
		} else {
			backdrop.style.opacity = 1;
		}
		drawer.querySelector('[data-cart-close].icon-btn').focus();
		call({ action: 'get' }).catch(() => {});
	}

	function close() {
		if (!drawer.classList.contains('is-open')) return;
		const done = () => {
			drawer.classList.remove('is-open');
			drawer.setAttribute('aria-hidden', 'true');
			document.body.style.overflow = '';
			lastFocus && lastFocus.focus && lastFocus.focus();
		};
		if (hasGsap) {
			gsap.to(backdrop, { opacity: 0, duration: 0.25 });
			gsap.to(panel, { xPercent: 110, duration: 0.35, ease: 'power2.in', onComplete: done });
		} else {
			backdrop.style.opacity = 0;
			done();
		}
	}

	document.addEventListener('click', async (ev) => {
		if (ev.target.closest('[data-cart-open]')) return open();
		if (ev.target.closest('[data-cart-close]')) return close();
		if (ev.target.closest('[data-cart-clear]')) {
			const ok = await CD.confirm({ title: 'Clear your cart?', body: 'All items will be removed.', confirmText: 'Clear cart', danger: true });
			if (ok) call({ action: 'clear' }).catch(() => {});
			return;
		}
		const step = ev.target.closest('.line [data-step]');
		if (step) {
			const line = step.closest('.line');
			const item = summary.items.find((i) => i.cartId === line.dataset.id);
			const qty = item.quantity + Number(step.dataset.step);
			if (qty <= 0 && hasGsap) await gsap.to(line, { x: 40, opacity: 0, duration: 0.2 });
			call({ action: 'qty', cart_id: item.cartId, quantity: qty }).catch(() => {});
		}
	});
	document.addEventListener('keydown', (ev) => ev.key === 'Escape' && drawer.classList.contains('is-open') && close());

	/* mobile nav toggle lives here too: both belong to the shop header */
	document.addEventListener('click', (ev) => {
		const t = ev.target.closest('[data-nav-toggle]');
		if (!t) return;
		const nav = t.closest('.topnav');
		const open = nav.classList.toggle('is-open');
		t.setAttribute('aria-expanded', open);
		t.innerHTML = icon(open ? 'x' : 'menu');
	});

	CD.cart = {
		open,
		close,
		refresh: () => call({ action: 'get' }),
		get summary() {
			return summary;
		},
		async add(payload) {
			const s = await call({ action: 'add', ...payload });
			const btn = document.querySelector('[data-cart-open]');
			if (btn && hasGsap) gsap.fromTo(btn, { rotate: -14 }, { rotate: 0, duration: 0.6, ease: 'elastic.out(1, .35)' });
			return s;
		},
		promo: (code) => call({ action: 'promo', code }),
		removePromo: () => call({ action: 'promo_remove' }),
	};

	call({ action: 'get' }).catch(() => {});
})();
