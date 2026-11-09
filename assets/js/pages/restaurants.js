/* Cafés & menu editor: café list, detail panel, café + dish forms with image preview (multipart AJAX). */
(function () {
	'use strict';
	const { api, esc, icon, toast, modal } = CD;
	const API = 'api/admin/restaurants.php';
	const listEl = document.getElementById('cafe-list');
	const detail = document.getElementById('rest-detail');
	const cafeForm = document.getElementById('cafe-form');
	const foodForm = document.getElementById('food-form');
	const money = (n) => 'RM' + (+n).toFixed(2);
	let cafes = [];
	let current = null; // { cafe, foods }
	let foodFilter = '';

	/* ---------- image inputs: live preview ---------- */
	function bindImage(form) {
		const input = form.querySelector('input[type=file]');
		const img = form.querySelector('.image-drop img');
		input.addEventListener('change', () => {
			const f = input.files[0];
			if (!f) return;
			if (f.size > 3 * 1024 * 1024) {
				toast('Image must be under 3 MB.', 'error');
				input.value = '';
				return;
			}
			img.src = URL.createObjectURL(f);
			img.hidden = false;
		});
	}
	function setPreview(form, src) {
		const img = form.querySelector('.image-drop img');
		img.hidden = !src;
		if (src) {
			img.onerror = () => (img.hidden = true);
			img.src = src;
		}
	}
	bindImage(cafeForm);
	bindImage(foodForm);

	/* ---------- list ---------- */
	function renderList() {
		const q = document.getElementById('cafe-filter').value.trim().toLowerCase();
		const rows = cafes.filter((c) => !q || c.Name.toLowerCase().includes(q));
		listEl.innerHTML = rows.length
			? rows
					.map(
						(c) => `<button type="button" class="rest-item ${current && current.cafe.Restaurant_ID === c.Restaurant_ID ? 'is-active' : ''}" data-id="${c.Restaurant_ID}" role="option" aria-selected="${!!(current && current.cafe.Restaurant_ID === c.Restaurant_ID)}">
				<span class="rest-thumb"><img src="${esc(c.image)}" alt="" loading="lazy" onerror="this.remove()"></span>
				<span><strong>${esc(c.Name)}</strong><small>${esc(c.Restaurant_type)} · ${c.dishes} dishes · ${c.orders} orders</small></span>
			</button>`
					)
					.join('')
			: '<div class="empty">No cafés match.</div>';
	}

	async function loadList() {
		const d = await api(API, { action: 'list' });
		cafes = d.cafes;
		document.getElementById('food-types').innerHTML = d.food_types.map((t) => `<option>${esc(t)}</option>`).join('');
		renderList();
		CD.stagger(listEl.children);
	}

	/* ---------- detail ---------- */
	function renderDetail(animate) {
		const { cafe: c, foods } = current;
		const q = foodFilter.toLowerCase();
		const shown = foods.filter((f) => !q || f.Name.toLowerCase().includes(q) || f.Food_type.toLowerCase().includes(q));
		detail.innerHTML = `
		<div class="glass card rest-head">
			<div class="rest-cover"><img src="${esc(c.image)}" alt="" onerror="this.remove()"></div>
			<div class="rest-meta">
				<span class="eyebrow">${esc(c.Restaurant_type)}</span>
				<h2>${esc(c.Name)}</h2>
				<p class="muted">${esc(c.Address || '')}</p>
				<p class="tiny">${esc(c.Email || '')} · ${esc(c.Contain_number || '')} · ★ ${(+c.Rating || 0).toFixed(1)}</p>
				<div class="rest-actions">
					<button class="btn btn-sm btn-glass" data-act="edit-cafe">${icon('edit')}Edit details</button>
					<a class="btn btn-sm btn-ghost" href="${CD.url('customer/cafe.php?id=' + c.Restaurant_ID)}" target="_blank" rel="noopener">View as customer ↗</a>
					<button class="btn btn-sm btn-danger" data-act="delete-cafe">${icon('trash')}Delete</button>
				</div>
			</div>
		</div>
		<div class="glass table-card">
			<div class="table-toolbar">
				<h3>Menu <span class="tiny">${foods.length} dishes</span></h3>
				<label class="search search-sm">${icon('search')}<span class="sr-only">Filter dishes</span><input class="input" type="search" data-food-filter value="${esc(foodFilter)}" placeholder="Filter dishes…"></label>
				<button class="btn btn-sm btn-primary" data-act="add-food">${icon('plus')}Add dish</button>
			</div>
			<div class="table-scroll"><table class="table">
				<thead><tr><th>Dish</th><th>Category</th><th>Price</th><th><span class="sr-only">Actions</span></th></tr></thead>
				<tbody>${
					shown.length
						? shown
								.map(
									(f) => `<tr data-food="${f.Food_ID}">
						<td><div class="who"><span class="dish-thumb"><img src="${esc(f.image)}" alt="" loading="lazy" onerror="this.remove()"></span><div><strong>${esc(f.Name)}</strong><small>${esc(f.detail || '')}</small></div></div></td>
						<td><span class="pill pill-muted">${esc(f.Food_type)}</span></td>
						<td class="num"><strong>${money(f.amount)}</strong></td>
						<td style="text-align:right;white-space:nowrap"><button class="icon-btn" data-act="edit-food" aria-label="Edit ${esc(f.Name)}">${icon('edit')}</button><button class="icon-btn danger" data-act="delete-food" aria-label="Delete ${esc(f.Name)}">${icon('trash')}</button></td>
					</tr>`
								)
								.join('')
						: `<tr><td colspan="4"><div class="empty">${foods.length ? 'No dishes match.' : 'No dishes yet — add the first one.'}</div></td></tr>`
				}</tbody>
			</table></div>
		</div>`;
		if (animate && window.gsap && !CD.reduced) gsap.from(detail.children, { y: 20, opacity: 0, stagger: 0.08, duration: 0.6, ease: 'expo.out' });
	}

	async function select(id) {
		try {
			current = await api(API, { action: 'get', Restaurant_ID: id });
			foodFilter = '';
			renderList();
			renderDetail(true);
		} catch (err) {
			toast(err.message, 'error');
		}
	}

	/* ---------- forms ---------- */
	function openCafe(c) {
		CD.clearErrors(cafeForm);
		cafeForm.reset();
		cafeForm.elements.Restaurant_ID.value = c ? c.Restaurant_ID : '';
		document.getElementById('cafe-title').textContent = c ? `Edit ${c.Name}` : 'Add café';
		if (c) CD.fillForm(cafeForm, c);
		setPreview(cafeForm, c ? c.image : null);
		modal.open('cafe-modal');
	}

	function openFood(f) {
		CD.clearErrors(foodForm);
		foodForm.reset();
		foodForm.elements.Restaurant_ID.value = current.cafe.Restaurant_ID;
		foodForm.elements.Food_ID.value = f ? f.Food_ID : '';
		document.getElementById('food-title').textContent = f ? `Edit ${f.Name}` : 'Add dish';
		document.getElementById('food-sub').textContent = current.cafe.Name;
		if (f) CD.fillForm(foodForm, f);
		setPreview(foodForm, f ? f.image + '?v=' + Date.now() : null);
		modal.open('food-modal');
	}

	CD.ajaxForm(cafeForm, API, {
		onSuccess: async (c) => {
			const isNew = !cafeForm.elements.Restaurant_ID.value;
			modal.close('cafe-modal');
			await loadList();
			await select(c.Restaurant_ID);
			toast(isNew ? `${c.Name} added` : 'Café updated');
		},
	});
	CD.ajaxForm(foodForm, API, {
		onSuccess: (d) => {
			const isNew = !foodForm.elements.Food_ID.value;
			modal.close('food-modal');
			current = d;
			renderDetail(false);
			const c = cafes.find((x) => x.Restaurant_ID === d.cafe.Restaurant_ID);
			if (c) c.dishes = d.foods.length;
			renderList();
			toast(isNew ? 'Dish added' : 'Dish updated');
		},
	});

	/* ---------- clicks ---------- */
	listEl.addEventListener('click', (ev) => {
		const b = ev.target.closest('[data-id]');
		if (b) select(+b.dataset.id);
	});
	document.getElementById('cafe-filter').addEventListener('input', renderList);
	document.querySelector('[data-cafe-new]').addEventListener('click', () => openCafe(null));
	detail.addEventListener('input', (ev) => {
		if (!ev.target.matches('[data-food-filter]')) return;
		foodFilter = ev.target.value;
		const pos = ev.target.selectionStart;
		renderDetail(false);
		const input = detail.querySelector('[data-food-filter]');
		input.focus();
		input.setSelectionRange(pos, pos);
	});
	detail.addEventListener('click', async (ev) => {
		const btn = ev.target.closest('[data-act]');
		if (!btn) return;
		const c = current.cafe;
		const food = current.foods.find((f) => String(f.Food_ID) === btn.closest('tr')?.dataset.food);
		switch (btn.dataset.act) {
			case 'edit-cafe':
				return openCafe(c);
			case 'add-food':
				return openFood(null);
			case 'edit-food':
				return openFood(food);
			case 'delete-food': {
				const ok = await CD.confirm({ title: `Delete ${esc(food.Name)}?`, body: 'It disappears from the menu. Past orders keep its name and price.', confirmText: 'Delete dish', danger: true });
				if (!ok) return;
				try {
					current = await api(API, { action: 'delete_food', Restaurant_ID: c.Restaurant_ID, Food_ID: food.Food_ID });
					renderDetail(false);
					toast('Dish deleted');
				} catch (err) {
					toast(err.message, 'error');
				}
				return;
			}
			case 'delete-cafe': {
				if (+c.orders > 0) return toast(`${c.Name} has ${c.orders} orders on record, so it can’t be deleted.`, 'error');
				const ok = await CD.confirm({ title: `Delete ${esc(c.Name)}?`, body: `This removes the café and its ${current.foods.length} dishes.`, confirmText: 'Delete café', danger: true });
				if (!ok) return;
				try {
					await api(API, { action: 'delete_cafe', Restaurant_ID: c.Restaurant_ID });
					current = null;
					detail.innerHTML = `<div class="glass card empty-detail">${icon('store')}<p>Café deleted. Select another café.</p></div>`;
					await loadList();
					toast(`${c.Name} deleted`);
				} catch (err) {
					toast(err.message, 'error');
				}
			}
		}
	});

	loadList()
		.then(() => cafes[0] && select(cafes[0].Restaurant_ID))
		.catch((err) => toast(err.message, 'error'));
})();
