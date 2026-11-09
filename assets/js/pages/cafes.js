/* Café browser: first paint from embedded JSON, then live server search (cafés + dishes). */
(function () {
	'use strict';
	const { api, esc, icon } = CD;
	const grid = document.getElementById('cafe-grid');
	const q = document.getElementById('cafe-q');
	const sort = document.getElementById('cafe-sort');
	const result = document.getElementById('cafe-result');
	let type = '';
	let req = 0;

	const money = (n) => 'RM' + (+n).toFixed(2);

	function card(c) {
		const matches = (c.matches || [])
			.map((m) => `<li><span>${esc(m.Name)}</span><b>${money(m.amount)}</b></li>`)
			.join('');
		return `
		<a class="cafe glass card-glow" href="${esc(c.url)}">
			<div class="cafe-img"><img loading="lazy" src="${esc(c.image)}" alt=""></div>
			<div class="cafe-body">
				<div>
					<h3>${esc(c.Name)}</h3>
					<span class="tiny">${esc(c.Restaurant_type)} · ${+c.dishes} dishes${c.from_price ? ' · from ' + money(c.from_price) : ''}</span>
				</div>
				<span class="rating">${icon('star')}${(+c.Rating || 0).toFixed(1)}</span>
			</div>
			${matches ? `<ul class="cafe-matches">${matches}</ul>` : ''}
		</a>`;
	}

	function render(list, query) {
		grid.innerHTML = list.length
			? list.map(card).join('')
			: `<div class="empty glass" style="grid-column:1/-1">Nothing matches “${esc(query)}”. Try a dish like “latte” or “pasta”.</div>`;
		result.textContent = `${list.length} ${list.length === 1 ? 'café' : 'cafés'}${query ? ` for “${query}”` : ''}`;
		CD.stagger(grid.children);
	}

	async function search() {
		const id = ++req;
		const query = q.value.trim();
		grid.setAttribute('aria-busy', 'true');
		try {
			const list = await api('api/shop/search.php', { action: 'search', q: query, type, sort: sort.value });
			if (id === req) render(list, query);
		} catch (err) {
			CD.toast(err.message, 'error');
		} finally {
			grid.removeAttribute('aria-busy');
		}
	}

	let t;
	q.addEventListener('input', () => {
		clearTimeout(t);
		t = setTimeout(search, 220);
	});
	sort.addEventListener('change', search);
	document.querySelectorAll('[data-type]').forEach((chip) =>
		chip.addEventListener('click', () => {
			type = chip.dataset.type;
			document.querySelectorAll('[data-type]').forEach((c) => {
				c.classList.toggle('is-active', c === chip);
				c.setAttribute('aria-checked', c === chip);
			});
			search();
		})
	);
	document.addEventListener('keydown', (ev) => {
		if (ev.key === '/' && !/input|textarea|select/i.test(document.activeElement.tagName)) {
			ev.preventDefault();
			q.focus();
		}
	});

	render(JSON.parse(document.getElementById('cafe-data').textContent), '');
})();
