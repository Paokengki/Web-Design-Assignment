/* Café menu: dish options modal, add to cart, sticky category tabs that follow the scroll. */
(function () {
	'use strict';
	const form = document.getElementById('dish-form');
	if (!form) return;
	const qtyOut = document.getElementById('dish-qty');
	const totalOut = document.getElementById('dish-total');
	const money = (n) => 'RM' + (+n).toFixed(2);
	let dish = null;
	let qty = 1;

	function updateTotal() {
		qtyOut.textContent = qty;
		totalOut.textContent = money(dish.price * qty);
	}

	document.addEventListener('click', (ev) => {
		const btn = ev.target.closest('[data-dish]');
		if (btn) {
			dish = JSON.parse(btn.dataset.dish);
			qty = 1;
			form.reset();
			form.elements.food_id.value = dish.id;
			document.getElementById('dish-title').textContent = dish.name;
			document.getElementById('dish-detail').textContent = dish.detail || dish.type;
			document.getElementById('dish-price').textContent = money(dish.price);
			const img = document.getElementById('dish-img');
			img.parentNode.hidden = false;
			img.onerror = () => (img.parentNode.hidden = true);
			img.src = dish.image;
			document.getElementById('drink-options').hidden = !dish.drink;
			updateTotal();
			CD.modal.open('dish-modal');
		}
		const step = ev.target.closest('[data-qty]');
		if (step && dish) {
			qty = Math.max(1, Math.min(20, qty + Number(step.dataset.qty)));
			updateTotal();
		}
	});

	form.addEventListener('submit', async (ev) => {
		ev.preventDefault();
		const btn = form.querySelector('[type=submit]');
		CD.busy(btn, true);
		const fd = new FormData(form);
		try {
			await CD.cart.add({
				food_id: dish.id,
				quantity: qty,
				sugar: dish.drink ? fd.get('sugar') : '',
				ice: dish.drink ? fd.get('ice') : '',
				remark: fd.get('remark') || '',
			});
			CD.modal.close('dish-modal');
			CD.toast(`${qty} × ${dish.name} added`);
		} catch (e) {
			/* toast already shown */
		} finally {
			CD.busy(btn, false);
		}
	});

	/* category tabs: highlight the section in view */
	const tabs = document.querySelector('[data-cat-tabs]');
	if (tabs && 'IntersectionObserver' in window) {
		const links = [...tabs.querySelectorAll('a')];
		const io = new IntersectionObserver(
			(entries) => {
				entries.forEach((en) => {
					if (!en.isIntersecting) return;
					links.forEach((a) => a.classList.toggle('is-active', a.getAttribute('href') === '#' + en.target.id));
					const active = tabs.querySelector('.is-active');
					active && active.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
				});
			},
			{ rootMargin: '-40% 0px -55% 0px' }
		);
		document.querySelectorAll('[data-cat]').forEach((s) => io.observe(s));
	}
})();
