/* Members table: server-side search/filter/sort/paging; reversible status changes via the row menu. */
(function () {
	'use strict';
	const { api, esc, icon, toast } = CD;
	const API = 'api/admin/members.php';
	const filters = document.getElementById('member-filters');
	const tbody = document.querySelector('#member-table tbody');
	const summary = document.getElementById('member-summary');
	const STATUS = { Active: 'pill-ok', Suspended: 'pill-warn', Deactivated: 'pill-bad' };
	const money = (n) => 'RM' + (+n).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	const date = (s) => (s ? new Date(s.replace(' ', 'T')).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' }) : '—');
	const initials = (n) => n.split(/[\s_]+/).slice(0, 2).map((p) => p[0] || '').join('').toUpperCase();
	let state = { page: 1, rows: [] };
	let req = 0;

	function row(r) {
		return `<tr data-id="${r.User_ID}">
			<td><div class="who"><span class="avatar avatar-sm">${r.avatar ? `<img src="${esc(r.avatar)}" alt="">` : esc(initials(r.User_Name))}</span>
				<div><strong>${esc(r.User_Name)}</strong><small>${esc(r.Email || 'no email')}${r.Contain_number ? ' · ' + esc(r.Contain_number) : ''}</small></div></div></td>
			<td><span class="pill ${STATUS[r.status]}"><span class="dot"></span>${r.status}</span></td>
			<td><span class="tier tier-${esc(r.tier)}">${esc(r.tier)}</span></td>
			<td class="num">${r.orders}</td>
			<td class="num">${money(r.spent)}</td>
			<td class="num">${date(r.last_order)}</td>
			<td class="num">${date(r.Created_at)}</td>
			<td style="text-align:right"><button class="icon-btn" data-menu-trigger aria-haspopup="menu" aria-label="Actions for ${esc(r.User_Name)}">${icon('dots')}</button></td>
		</tr>`;
	}

	async function load(animate = true) {
		const id = ++req;
		const fd = new FormData(filters);
		fd.append('action', 'list');
		fd.append('page', state.page);
		try {
			const d = await api(API, fd);
			if (id !== req) return;
			Object.assign(state, d);
			tbody.innerHTML = d.rows.length ? d.rows.map(row).join('') : '<tr><td colspan="8"><div class="empty">No members match.</div></td></tr>';
			if (animate) CD.stagger(tbody.children);
			const start = d.total ? (d.page - 1) * d.per_page + 1 : 0;
			summary.textContent = `${start}–${Math.min(d.total, d.page * d.per_page)} of ${d.total} members`;
			document.querySelector('[data-page="prev"]').disabled = d.page <= 1;
			document.querySelector('[data-page="next"]').disabled = d.page >= d.pages;
			Object.entries(d.counts).forEach(([k, v]) => {
				const el = document.querySelector(`[data-count-status="${k}"]`);
				if (el && el.textContent !== String(v)) CD.countUp(el, v, { duration: 0.8 });
			});
		} catch (err) {
			toast(err.message, 'error');
		}
	}

	async function setStatus(r, status, confirmOpts) {
		if (confirmOpts && !(await CD.confirm(confirmOpts))) return;
		try {
			const updated = await api(API, { action: 'set_status', User_ID: r.User_ID, Status: status });
			const tr = tbody.querySelector(`tr[data-id="${r.User_ID}"]`);
			const tmp = document.createElement('tbody');
			tmp.innerHTML = row(updated);
			tmp.firstElementChild.classList.add('is-flash');
			tr.replaceWith(tmp.firstElementChild);
			state.rows[state.rows.findIndex((x) => x.User_ID === r.User_ID)] = updated;
			toast(`${updated.User_Name} is now ${updated.status.toLowerCase()}`);
			load(false); // refresh counts
		} catch (err) {
			toast(err.message, 'error');
		}
	}

	tbody.addEventListener('click', (ev) => {
		const t = ev.target.closest('[data-menu-trigger]');
		if (!t) return;
		const r = state.rows.find((x) => String(x.User_ID) === t.closest('tr').dataset.id);
		const items = [{ heading: r.User_Name }];
		if (r.status !== 'Active') items.push({ label: r.status === 'Deactivated' ? 'Restore account' : 'Lift suspension', icon: 'check', onClick: () => setStatus(r, 'Active') });
		if (r.status === 'Active')
			items.push({
				label: 'Suspend',
				icon: 'ban',
				danger: true,
				onClick: () => setStatus(r, 'Suspended', { title: `Suspend ${esc(r.User_Name)}?`, body: 'They can’t sign in or order until you lift it.', confirmText: 'Suspend', danger: true }),
			});
		if (r.status !== 'Deactivated')
			items.push({
				label: 'Deactivate account',
				icon: 'power',
				danger: true,
				onClick: () => setStatus(r, 'Deactivated', { title: `Deactivate ${esc(r.User_Name)}?`, body: `The account is hidden and locked. Their ${r.orders} orders stay on record, and you can restore it any time.`, confirmText: 'Deactivate', danger: true }),
			});
		if (r.Email) items.push({ sep: true }, { label: 'Copy email', icon: 'copy', onClick: () => CD.copy(r.Email) });
		CD.menu(t, items);
	});

	let deb;
	filters.addEventListener('input', (ev) => {
		clearTimeout(deb);
		state.page = 1;
		deb = setTimeout(load, ev.target.name === 'q' ? 250 : 0);
	});
	filters.addEventListener('reset', () => setTimeout(() => { state.page = 1; load(); }, 0));
	document.querySelectorAll('[data-page]').forEach((b) => b.addEventListener('click', () => { state.page += b.dataset.page === 'next' ? 1 : -1; load(); }));
	document.addEventListener('keydown', (ev) => {
		if (ev.key === '/' && !/input|textarea|select/i.test(document.activeElement.tagName)) {
			ev.preventDefault();
			filters.elements.q.focus();
		}
	});
	load();
})();
