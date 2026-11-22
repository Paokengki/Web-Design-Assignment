/* Reviews & ratings (admin): search/filter/sort/paging, reply, issue status, hide / restore. */
(function () {
	'use strict';
	const { api, esc, icon, toast, modal } = CD;
	const API = 'api/admin/reviews.php';

	const filters = document.getElementById('rv-filters');
	const tbody = document.querySelector('#rv-table tbody');
	const summary = document.getElementById('rv-summary');
	const replyForm = document.getElementById('reply-form');

	const STATUS_CLASS = { Pending: 'pill-warn', Investigating: 'pill-amber', Resolved: 'pill-ok' };
	const STATUSES = ['Pending', 'Investigating', 'Resolved'];

	let state = { page: 1, pages: 1, rows: [] };
	let requestId = 0;
	let cafesFilled = false;

	/* ---------------------------------------------- helpers */
	const stars = (n) => `<span class="stars" title="${n} out of 5">${'★'.repeat(n)}<span class="off">${'★'.repeat(5 - n)}</span></span>`;
	const fmt = (s) => (s ? new Date(s.replace(' ', 'T')).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' }) : '—');
	const hidden = (r) => Number(r.Is_hidden) === 1;

	/* ---------------------------------------------- render */
	function rowHtml(r) {
		return `
		<tr data-id="${r.Review_ID}" class="${hidden(r) ? 'is-hidden-row' : ''}">
			<td class="num">${fmt(r.Created_at)}${r.Edited_at ? '<br><small class="tiny">edited</small>' : ''}</td>
			<td><strong>${esc(r.customer)}</strong><br><small class="tiny">${esc(r.customer_email || '')}</small></td>
			<td>${esc(r.cafe_name)}</td>
			<td>${stars(Number(r.Rating))}</td>
			<td class="rv-text"><div class="rv-clamp">${esc(r.Comment)}</div>
				${r.Admin_reply ? `<div class="rv-reply-line">↳ Replied${r.Replied_by_name ? ' by ' + esc(r.Replied_by_name) : ''}: ${esc(r.Admin_reply.length > 80 ? r.Admin_reply.slice(0, 79) + '…' : r.Admin_reply)}</div>` : ''}</td>
			<td><span class="pill ${STATUS_CLASS[r.Issue_status]}"><span class="dot"></span>${esc(r.Issue_status)}</span>
				${hidden(r) ? '<br><span class="pill pill-bad" title="Hidden from the customer’s public view">Hidden</span>' : ''}</td>
			<td style="text-align:right"><button class="icon-btn" data-menu-trigger aria-haspopup="menu" aria-label="Actions for this review">${icon('dots')}</button></td>
		</tr>`;
	}

	function render(animate) {
		tbody.innerHTML = state.rows.length
			? state.rows.map(rowHtml).join('')
			: '<tr><td colspan="7"><div class="empty">No reviews match these filters.</div></td></tr>';
		if (animate) CD.stagger(tbody.children);
	}

	function showStats(s) {
		Object.entries(s).forEach(([key, val]) => {
			const el = document.querySelector(`[data-stat="${key}"]`);
			if (!el) return;
			if (key === 'avg_rating') el.textContent = s.total ? `${Number(val).toFixed(1)} ★` : '–';
			else if (el.textContent !== String(val)) CD.countUp(el, val, { duration: 0.8 });
		});
	}

	/* ---------------------------------------------- load (server does the filtering) */
	async function load(animate = true) {
		const id = ++requestId;
		const fd = new FormData(filters);
		fd.append('action', 'list');
		fd.append('page', state.page);
		try {
			const data = await api(API, fd);
			if (id !== requestId) return; // a newer search already started
			Object.assign(state, data);
			if (!cafesFilled) {
				const sel = filters.elements.cafe;
				data.cafes.forEach((c) => sel.add(new Option(c.Name, c.Restaurant_ID)));
				cafesFilled = true;
			}
			render(animate);
			showStats(data.stats);
			const start = data.total ? (data.page - 1) * data.per_page + 1 : 0;
			summary.textContent = `${start}–${Math.min(data.total, data.page * data.per_page)} of ${data.total} reviews`;
			document.querySelector('[data-page="prev"]').disabled = data.page <= 1;
			document.querySelector('[data-page="next"]').disabled = data.page >= data.pages;
		} catch (err) {
			toast(err.message, 'error');
		}
	}

	let debounce;
	filters.addEventListener('input', (ev) => {
		clearTimeout(debounce);
		state.page = 1;
		debounce = setTimeout(load, ev.target.name === 'q' ? 250 : 0);
	});
	filters.addEventListener('reset', () => setTimeout(() => { state.page = 1; load(); }, 0));
	document.querySelectorAll('[data-page]').forEach((b) =>
		b.addEventListener('click', () => {
			state.page += b.dataset.page === 'next' ? 1 : -1;
			load();
		})
	);
	document.addEventListener('keydown', (ev) => {
		if (ev.key === '/' && !/input|textarea|select/i.test(document.activeElement.tagName)) {
			ev.preventDefault();
			filters.elements.q.focus();
		}
	});

	/* ---------------------------------------------- reply */
	function openReply(r) {
		CD.clearErrors(replyForm);
		replyForm.reset();
		replyForm.elements.Review_ID.value = r.Review_ID;
		replyForm.elements.Admin_reply.value = r.Admin_reply || '';
		replyForm.elements.Issue_status.value = r.Issue_status;
		document.getElementById('reply-title').textContent = r.Admin_reply ? 'Edit reply' : 'Reply to review';
		document.getElementById('rv-preview').innerHTML =
			`<div>${stars(Number(r.Rating))} <small class="tiny">· ${esc(r.customer)} · ${esc(r.cafe_name)} · ${fmt(r.Created_at)}</small></div><p>${esc(r.Comment)}</p>`;
		modal.open('reply-modal');
	}

	CD.ajaxForm(replyForm, API, {
		onSuccess: async () => {
			modal.close('reply-modal');
			await load(false);
			toast('Reply saved');
		},
	});

	/* ---------------------------------------------- row actions */
	async function run(payload, success) {
		try {
			await api(API, payload);
			await load(false);
			toast(success);
		} catch (err) {
			toast(err.message, 'error');
		}
	}

	function openMenu(trigger, r) {
		const items = [
			{ label: r.Admin_reply ? 'Edit reply' : 'Reply to customer', icon: 'edit', onClick: () => openReply(r) },
			{ sep: true },
			{ heading: 'Set issue status' },
			...STATUSES.filter((s) => s !== r.Issue_status).map((s) => ({
				label: `Mark as ${s}`,
				icon: 'check',
				onClick: () => run({ action: 'set_status', Review_ID: r.Review_ID, Status: s }, `Marked as ${s}`),
			})),
			{ sep: true },
		];
		if (hidden(r)) {
			items.push({ label: 'Restore review', icon: 'check', onClick: () => run({ action: 'set_hidden', Review_ID: r.Review_ID, Hidden: 0 }, 'Review restored') });
		} else {
			items.push({
				label: 'Hide review',
				icon: 'ban',
				danger: true,
				onClick: async () => {
					const ok = await CD.confirm({ title: 'Hide this review?', body: 'It stays in the database and can be restored later, but it no longer counts towards the café’s rating.', confirmText: 'Hide', danger: true });
					if (ok) run({ action: 'set_hidden', Review_ID: r.Review_ID, Hidden: 1 }, 'Review hidden');
				},
			});
		}
		CD.menu(trigger, items);
	}

	tbody.addEventListener('click', (ev) => {
		const trigger = ev.target.closest('[data-menu-trigger]');
		if (!trigger) return;
		const r = state.rows.find((x) => String(x.Review_ID) === trigger.closest('tr').dataset.id);
		if (r) openMenu(trigger, r);
	});

	load();
})();
