/* Module 2 — Promotions board / grid. All changes go through api/admin/promos.php without reloading. */
(function () {
	'use strict';
	const { api, esc, icon, toast, modal } = CD;
	const API = 'api/admin/promos.php';

	const board = document.getElementById('promo-board');
	const grid = document.getElementById('promo-grid');
	const tbody = grid.querySelector('tbody');
	const form = document.getElementById('promo-form');
	const search = document.getElementById('promo-search');
	const showPaused = document.getElementById('promo-show-paused');

	let promos = [];
	let view = 'board';
	try { view = localStorage.getItem('promo-view') || 'board'; } catch (e) { /* storage blocked */ }

	const STATUS = {
		active: { label: 'Active', pill: 'pill-ok', color: 'var(--ok)' },
		scheduled: { label: 'Scheduled', pill: 'pill-info', color: 'var(--info)' },
		expired: { label: 'Expired', pill: 'pill-muted', color: 'var(--muted)' },
	};

	/* ---------------------------------------------- helpers */
	const toDate = (s) => new Date(String(s).replace(' ', 'T'));
	const toInput = (s) => String(s).replace(' ', 'T').slice(0, 16);
	const fmtDate = (s) => toDate(s).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' });
	const pct = (n) => (+n % 1 ? (+n).toFixed(1) : +n);

	function relative(p) {
		const now = Date.now();
		const fmt = (ms) => {
			const d = Math.round(Math.abs(ms) / 86400000);
			if (d >= 1) return `${d}d`;
			const h = Math.max(1, Math.round(Math.abs(ms) / 3600000));
			return `${h}h`;
		};
		if (p.status === 'scheduled') return `Starts in ${fmt(toDate(p.Starts_at) - now)}`;
		if (p.status === 'active') return `Ends in ${fmt(toDate(p.Expires_at) - now)}`;
		const max = p.Max_redemptions && +p.Redemption_count >= +p.Max_redemptions;
		return max ? 'Limit reached' : `Ended ${fmt(now - toDate(p.Expires_at))} ago`;
	}

	function usage(p) {
		const used = +p.Redemption_count;
		const max = p.Max_redemptions ? +p.Max_redemptions : null;
		const ratio = max ? Math.min(100, (used / max) * 100) : 0;
		return { used, max, ratio, text: max ? `${used.toLocaleString()} / ${max.toLocaleString()}` : `${used.toLocaleString()} · unlimited` };
	}

	function matches(p) {
		const q = search.value.trim().toLowerCase();
		if (!showPaused.checked && !+p.Is_active) return false;
		return !q || p.Code.toLowerCase().includes(q) || p.Title.toLowerCase().includes(q);
	}

	function actions(p) {
		const paused = !+p.Is_active;
		return `
			<button class="icon-btn" data-act="edit" title="Edit" aria-label="Edit ${esc(p.Code)}">${icon('edit')}</button>
			<button class="icon-btn" data-act="toggle" title="${paused ? 'Resume' : 'Deactivate'}" aria-label="${paused ? 'Resume' : 'Deactivate'} ${esc(p.Code)}">${icon(paused ? 'play' : 'pause')}</button>
			<button class="icon-btn danger" data-act="delete" title="Delete" aria-label="Delete ${esc(p.Code)}">${icon('trash')}</button>`;
	}

	/* ---------------------------------------------- render */
	function card(p) {
		const u = usage(p);
		const paused = !+p.Is_active;
		return `
		<article class="promo ${paused ? 'is-paused' : ''}" data-id="${p.Promo_ID}" style="--c:${STATUS[p.status].color}">
			<div class="promo-top">
				<button class="promo-code" data-act="copy" title="Copy code">${esc(p.Code)}</button>
				<div class="promo-discount">${pct(p.Discount_percent)}<small>% off</small></div>
			</div>
			<h4>${esc(p.Title)} ${paused ? '<span class="pill pill-warn">Paused</span>' : ''}</h4>
			<p>${esc(p.Description || '')}${+p.Min_spend > 0 ? ` · min RM${(+p.Min_spend).toFixed(0)}` : ''}</p>
			<div class="usage">
				<div class="usage-meta"><span>Redeemed</span><span class="mono">${u.text}</span></div>
				<div class="usage-bar"><i style="width:${u.max ? u.ratio : 100}%;${u.max ? '' : 'opacity:.25'}"></i></div>
			</div>
			<footer class="promo-foot">
				<span class="when" title="${fmtDate(p.Starts_at)} → ${fmtDate(p.Expires_at)}">${relative(p)}</span>
				<div class="promo-actions">${actions(p)}</div>
			</footer>
		</article>`;
	}

	function row(p) {
		const u = usage(p);
		const s = STATUS[p.status];
		const paused = !+p.Is_active;
		return `
		<tr data-id="${p.Promo_ID}">
			<td><button class="promo-code" data-act="copy" title="Copy code">${esc(p.Code)}</button></td>
			<td><strong>${esc(p.Title)}</strong><br><small class="tiny">${esc(p.Description || '')}</small></td>
			<td class="num"><strong>${pct(p.Discount_percent)}%</strong>${+p.Min_spend > 0 ? `<br><small class="tiny">min RM${(+p.Min_spend).toFixed(0)}</small>` : ''}</td>
			<td class="num" style="min-width:140px"><span class="mono tiny">${u.text}</span><div class="usage-bar" style="--c:${s.color};margin-top:6px"><i style="width:${u.max ? u.ratio : 100}%;${u.max ? '' : 'opacity:.25'}"></i></div></td>
			<td><small>${fmtDate(p.Starts_at)} → ${fmtDate(p.Expires_at)}</small><br><small class="tiny">${relative(p)}</small></td>
			<td><span class="pill ${s.pill}"><span class="dot"></span>${s.label}</span> ${paused ? '<span class="pill pill-warn">Paused</span>' : ''}</td>
			<td><div class="promo-actions">${actions(p)}</div></td>
		</tr>`;
	}

	function render(animate) {
		const visible = promos.filter(matches);
		['active', 'scheduled', 'expired'].forEach((key) => {
			const list = visible.filter((p) => p.status === key);
			const body = board.querySelector(`[data-col="${key}"] .column-body`);
			body.innerHTML = list.length ? list.map(card).join('') : `<div class="empty">No ${key} promotions</div>`;
			board.querySelector(`[data-count-col="${key}"]`).textContent = list.length;
			if (animate) CD.stagger(body.children);
		});
		tbody.innerHTML = visible.length ? visible.map(row).join('') : `<tr><td colspan="7"><div class="empty">No promotions match.</div></td></tr>`;
		if (animate && view === 'grid') CD.stagger(tbody.children);
	}

	function renderStats(stats) {
		Object.entries(stats).forEach(([k, v]) => {
			const el = document.querySelector(`[data-stat="${k}"]`);
			if (el) CD.countUp(el, v, { duration: 1 });
		});
	}

	function computeStats() {
		const s = { active: 0, scheduled: 0, expired: 0, redemptions: 0 };
		promos.forEach((p) => {
			s[p.status]++;
			s.redemptions += +p.Redemption_count;
		});
		return s;
	}

	function setView(v) {
		view = v;
		board.hidden = v !== 'board';
		grid.hidden = v !== 'grid';
		document.querySelectorAll('[data-view]').forEach((b) => {
			b.classList.toggle('is-active', b.dataset.view === v);
			b.setAttribute('aria-selected', b.dataset.view === v);
		});
		try { localStorage.setItem('promo-view', v); } catch (e) { /* ignore */ }
		render(true);
	}

	function upsert(p) {
		const i = promos.findIndex((x) => x.Promo_ID === p.Promo_ID);
		if (i >= 0) promos[i] = p;
		else promos.unshift(p);
		render(false);
		renderStats(computeStats());
		const el = document.querySelector(`${view === 'board' ? '.promo' : 'tr'}[data-id="${p.Promo_ID}"]`);
		if (el && window.gsap && !CD.reduced) {
			gsap.fromTo(el, { scale: 0.96, boxShadow: '0 0 0 2px rgba(245,165,36,.8)' }, { scale: 1, boxShadow: '0 0 0 0 rgba(245,165,36,0)', duration: 0.9, ease: 'expo.out', clearProps: 'all' });
			el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
		}
	}

	/* ---------------------------------------------- load */
	async function load() {
		try {
			const data = await api(API, { action: 'list' });
			promos = data.promos;
			renderStats(data.stats);
			setView(view);
		} catch (err) {
			toast(err.message, 'error');
		}
	}

	/* ---------------------------------------------- form */
	function randomCode() {
		const words = ['BREW', 'LATTE', 'MOCHA', 'CREMA', 'ROAST', 'SIP', 'BEAN', 'FOAM'];
		return words[Math.floor(Math.random() * words.length)] + Math.floor(10 + Math.random() * 90);
	}

	function openForm(p) {
		CD.clearErrors(form);
		form.reset();
		const editing = !!p;
		form.elements.action.value = editing ? 'update' : 'create';
		document.getElementById('promo-title').textContent = editing ? `Edit ${p.Code}` : 'New promotion';
		if (editing) {
			CD.fillForm(form, { ...p, Starts_at: toInput(p.Starts_at), Expires_at: toInput(p.Expires_at), Discount_percent: pct(p.Discount_percent), Min_spend: (+p.Min_spend).toFixed(2) });
		} else {
			const start = new Date(Date.now() - new Date().getTimezoneOffset() * 60000);
			const end = new Date(start.getTime() + 14 * 86400000);
			form.elements.Promo_ID.value = '';
			form.elements.Starts_at.value = start.toISOString().slice(0, 16);
			form.elements.Expires_at.value = end.toISOString().slice(0, 16);
			form.elements.Min_spend.value = '0';
		}
		modal.open('promo-modal');
	}

	CD.ajaxForm(form, API, {
		onSuccess: (p) => {
			const wasEdit = form.elements.action.value === 'update';
			modal.close('promo-modal');
			upsert(p);
			toast(wasEdit ? `${p.Code} updated` : `${p.Code} created — ${STATUS[p.status].label.toLowerCase()}`);
		},
	});

	document.getElementById('promo-generate').addEventListener('click', () => {
		form.elements.Code.value = randomCode();
		form.elements.Code.dispatchEvent(new Event('input'));
	});

	/* ---------------------------------------------- actions */
	document.addEventListener('click', async (ev) => {
		if (ev.target.closest('[data-promo-new]')) return openForm(null);
		const viewBtn = ev.target.closest('[data-view]');
		if (viewBtn) return setView(viewBtn.dataset.view);

		const btn = ev.target.closest('[data-act]');
		const host = btn && btn.closest('[data-id]');
		if (!host || !(board.contains(host) || grid.contains(host))) return;
		const p = promos.find((x) => String(x.Promo_ID) === host.dataset.id);
		if (!p) return;

		switch (btn.dataset.act) {
			case 'copy':
				return CD.copy(p.Code);
			case 'edit':
				return openForm(p);
			case 'toggle':
				CD.busy(btn, true);
				try {
					const updated = await api(API, { action: 'toggle', Promo_ID: p.Promo_ID });
					upsert(updated);
					toast(+updated.Is_active ? `${p.Code} is live again` : `${p.Code} deactivated`, +updated.Is_active ? 'success' : 'info');
				} catch (err) {
					toast(err.message, 'error');
					CD.busy(btn, false);
				}
				return;
			case 'delete': {
				const ok = await CD.confirm({
					title: `Delete ${p.Code}?`,
					body: `This removes the code and its ${(+p.Redemption_count).toLocaleString()} redemption records. To keep history, deactivate it instead.`,
					confirmText: 'Delete permanently',
					danger: true,
				});
				if (!ok) return;
				try {
					if (window.gsap && !CD.reduced) await gsap.to(host, { x: 30, opacity: 0, duration: 0.25, ease: 'power2.in' });
					await api(API, { action: 'delete', Promo_ID: p.Promo_ID });
					promos = promos.filter((x) => x !== p);
					render(false);
					renderStats(computeStats());
					toast(`${p.Code} deleted`);
				} catch (err) {
					render(false);
					toast(err.message, 'error');
				}
			}
		}
	});

	let t;
	search.addEventListener('input', () => {
		clearTimeout(t);
		t = setTimeout(() => render(true), 120);
	});
	showPaused.addEventListener('change', () => render(true));
	document.addEventListener('keydown', (ev) => {
		if (ev.key === '/' && !/input|textarea|select/i.test(document.activeElement.tagName)) {
			ev.preventDefault();
			search.focus();
		}
	});

	load();
})();
