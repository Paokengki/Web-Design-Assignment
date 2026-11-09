/* Overview: KPI tiles, daily revenue columns (SVG), café ranking bars, best sellers.
 * Single-series charts: one hue (amber), no legend box — the card title names the series.
 * Every mark has a hover tooltip; every chart has a table view. */
(function () {
	'use strict';
	const { api, esc } = CD;
	const tip = document.getElementById('chart-tip');
	const money = (n) => 'RM' + (+n).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	const short = (n) => (n >= 10000 ? (n / 1000).toFixed(0) + 'K' : Math.round(n).toLocaleString('en-MY'));
	const day = (d) => new Date(d + 'T00:00').toLocaleDateString('en-MY', { day: 'numeric', month: 'short' });
	let data = null;

	/* ---------------- tooltip ---------------- */
	function showTip(html, x, y) {
		tip.innerHTML = html;
		tip.hidden = false;
		const w = tip.offsetWidth;
		tip.style.left = Math.min(window.innerWidth - w - 12, Math.max(12, x - w / 2)) + 'px';
		tip.style.top = y - tip.offsetHeight - 12 + 'px';
	}
	const hideTip = () => (tip.hidden = true);

	/* ---------------- KPIs ---------------- */
	function delta(now, prev) {
		if (!prev) return now ? '<span class="up">new</span>' : '';
		const pct = ((now - prev) / prev) * 100;
		const cls = pct >= 0 ? 'up' : 'down';
		return `<span class="${cls}">${pct >= 0 ? '▲' : '▼'} ${Math.abs(pct).toFixed(0)}%</span> vs previous ${data.days} days`;
	}
	function renderKpis(k) {
		CD.countUp(document.querySelector('[data-kpi="revenue"]'), k.revenue, { prefix: 'RM', decimals: 2, duration: 1.2 });
		['orders', 'members', 'active_promos', 'unread'].forEach((key) => CD.countUp(document.querySelector(`[data-kpi="${key}"]`), k[key], { duration: 1 }));
		document.querySelector('[data-delta="revenue"]').innerHTML = delta(k.revenue, k.revenue_prev);
		document.querySelector('[data-delta="orders"]').innerHTML = delta(k.orders, k.orders_prev);
		document.querySelector('[data-kpi-note="buyers"]').textContent = `${k.buyers} ordered in this period`;
	}

	/* ---------------- revenue columns ---------------- */
	function niceMax(v) {
		if (v <= 0) return 100;
		const p = Math.pow(10, Math.floor(Math.log10(v)));
		return [1, 2, 2.5, 5, 10].map((m) => m * p).find((m) => m >= v);
	}

	function renderRevenue(series) {
		const el = document.getElementById('rev-chart');
		const W = el.clientWidth || 600, H = 260, padL = 48, padB = 28, padT = 12;
		const innerW = W - padL - 8, innerH = H - padB - padT;
		const max = niceMax(Math.max(...series.map((s) => s.revenue)));
		const band = innerW / series.length;
		const bw = Math.min(24, band - 2); // thin columns, never fill the slot
		const y = (v) => padT + innerH - (v / max) * innerH;
		const ticks = [0, 0.25, 0.5, 0.75, 1].map((t) => t * max);
		const every = series.length > 14 ? Math.ceil(series.length / 8) : series.length > 7 ? 2 : 1;

		let svg = `<svg viewBox="0 0 ${W} ${H}" width="100%" height="${H}">`;
		ticks.forEach((t) => {
			svg += `<line class="grid" x1="${padL}" x2="${W - 8}" y1="${y(t)}" y2="${y(t)}"/><text class="axis" x="${padL - 8}" y="${y(t) + 4}" text-anchor="end">${short(t)}</text>`;
		});
		series.forEach((s, i) => {
			const cx = padL + band * i + band / 2;
			const h = Math.max(s.revenue > 0 ? 2 : 0, innerH - (y(s.revenue) - padT));
			const top = padT + innerH - h;
			const r = Math.min(4, h); // rounded data end, square at the baseline
			if (h > 0) svg += `<path class="col" d="M${cx - bw / 2},${padT + innerH} V${top + r} q0,-${r} ${r},-${r} H${cx + bw / 2 - r} q${r},0 ${r},${r} V${padT + innerH} Z"/>`;
			svg += `<rect class="hit" x="${padL + band * i}" y="${padT}" width="${band}" height="${innerH}" data-i="${i}"/>`;
			if (i % every === 0 || i === series.length - 1) svg += `<text class="axis" x="${cx}" y="${H - 8}" text-anchor="middle">${day(s.date)}</text>`;
		});
		svg += `<line class="baseline" x1="${padL}" x2="${W - 8}" y1="${padT + innerH}" y2="${padT + innerH}"/></svg>`;
		el.innerHTML = svg;

		el.querySelectorAll('.hit').forEach((h) => {
			h.addEventListener('mouseenter', () => {
				const s = series[+h.dataset.i];
				h.classList.add('on');
				const r = h.getBoundingClientRect();
				showTip(`<b>${day(s.date)}</b><span>${money(s.revenue)}</span><small>${s.orders} order${s.orders === 1 ? '' : 's'}</small>`, r.left + r.width / 2, r.top + window.scrollY + (y(s.revenue) - padT));
			});
			h.addEventListener('mouseleave', () => {
				h.classList.remove('on');
				hideTip();
			});
		});
		if (window.gsap && !CD.reduced) gsap.from(el.querySelectorAll('.col'), { scaleY: 0, transformOrigin: 'bottom', duration: 0.8, ease: 'expo.out', stagger: 0.02 });

		document.getElementById('rev-sub').textContent = `RM, last ${series.length} days`;
		document.getElementById('rev-table').innerHTML = `<table class="table"><thead><tr><th>Date</th><th>Orders</th><th>Revenue</th></tr></thead><tbody>${series
			.map((s) => `<tr><td>${day(s.date)}</td><td class="num">${s.orders}</td><td class="num">${money(s.revenue)}</td></tr>`)
			.join('')}</tbody></table>`;
	}

	/* ---------------- ranking bars ---------------- */
	function renderRanking(rows) {
		const max = Math.max(1, ...rows.map((r) => r.orders));
		const el = document.getElementById('rank-chart');
		el.innerHTML = rows
			.map(
				(r, i) => `
			<div class="hbar" tabindex="0" data-i="${i}">
				<span class="hbar-label"><b>${i + 1}</b>${esc(r.name)}</span>
				<span class="hbar-track"><i style="width:${(r.orders / max) * 100}%"></i></span>
				<span class="hbar-value">${r.orders}</span>
			</div>`
			)
			.join('');
		el.querySelectorAll('.hbar').forEach((b) => {
			const r = rows[+b.dataset.i];
			const show = () => {
				const rect = b.querySelector('.hbar-track').getBoundingClientRect();
				showTip(`<b>${esc(r.name)}</b><span>${r.orders} orders</span><small>${money(r.revenue)} revenue</small>`, rect.left + rect.width / 2, rect.top + window.scrollY);
			};
			b.addEventListener('mouseenter', show);
			b.addEventListener('focus', show);
			b.addEventListener('mouseleave', hideTip);
			b.addEventListener('blur', hideTip);
		});
		if (window.gsap && !CD.reduced) gsap.from(el.querySelectorAll('.hbar-track i'), { width: 0, duration: 1, ease: 'expo.out', stagger: 0.04 });
		document.getElementById('rank-table').innerHTML = `<table class="table"><thead><tr><th>#</th><th>Café</th><th>Orders</th><th>Revenue</th></tr></thead><tbody>${rows
			.map((r, i) => `<tr><td>${i + 1}</td><td>${esc(r.name)}</td><td class="num">${r.orders}</td><td class="num">${money(r.revenue)}</td></tr>`)
			.join('')}</tbody></table>`;
	}

	function renderTop(items) {
		const el = document.getElementById('top-items');
		el.innerHTML = items.length
			? items.map((it) => `<li><span>${esc(it.name)}</span><b>${it.qty}</b></li>`).join('')
			: '<li class="empty">No sales yet.</li>';
	}

	async function load(days) {
		try {
			data = await api('api/admin/overview.php', { action: 'stats', days });
			renderKpis(data.kpi);
			renderRevenue(data.series);
			renderRanking(data.ranking);
			renderTop(data.top_items);
		} catch (err) {
			CD.toast(err.message, 'error');
		}
	}

	document.querySelectorAll('[data-days]').forEach((b) =>
		b.addEventListener('click', () => {
			document.querySelectorAll('[data-days]').forEach((x) => {
				x.classList.toggle('is-active', x === b);
				x.setAttribute('aria-checked', x === b);
			});
			load(b.dataset.days);
		})
	);
	document.querySelectorAll('[data-toggle-table]').forEach((b) =>
		b.addEventListener('click', () => {
			const key = b.dataset.toggleTable;
			const table = document.getElementById(key + '-table');
			const chart = document.getElementById(key + '-chart');
			table.hidden = !table.hidden;
			chart.hidden = !table.hidden;
			b.textContent = table.hidden ? 'Table view' : 'Chart view';
		})
	);
	let rt;
	window.addEventListener('resize', () => {
		clearTimeout(rt);
		rt = setTimeout(() => data && renderRevenue(data.series), 150);
	});
	load(14);
})();
