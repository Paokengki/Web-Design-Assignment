/* Module 3 — Staff table: server-side search/filter/sort/paging, row action menu, AJAX updates. */
(function () {
	'use strict';
	const { api, esc, icon, toast, modal } = CD;
	const API = 'api/admin/staff.php';

	const filters = document.getElementById('staff-filters');
	const tbody = document.querySelector('#staff-table tbody');
	const summary = document.getElementById('staff-summary');
	const form = document.getElementById('staff-form');

	const ROLE_CLASS = { 'Super Admin': 'role-super', Manager: 'role-manager', 'Kitchen Staff': 'role-kitchen', Courier: 'role-courier' };
	const STATUS_CLASS = { Active: 'pill-ok', Suspended: 'pill-warn', Deactivated: 'pill-bad' };
	const ASSIGNABLE = ['Manager', 'Kitchen Staff', 'Courier'];

	let state = { page: 1, pages: 1, rows: [], me: 0 };
	let requestId = 0;

	/* ---------------------------------------------- helpers */
	const initials = (n) => n.split(/[\s_]+/).slice(0, 2).map((p) => p[0] || '').join('').toUpperCase() || '?';
	const fmt = (s) => (s ? new Date(s.replace(' ', 'T')).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' }) : '—');
	const ago = (s) => {
		if (!s) return '<span class="tiny">Never</span>';
		const m = Math.round((Date.now() - new Date(s.replace(' ', 'T'))) / 60000);
		if (m < 60) return `${Math.max(1, m)}m ago`;
		if (m < 1440) return `${Math.round(m / 60)}h ago`;
		return `${Math.round(m / 1440)}d ago`;
	};
	const locked = (r) => r.Role === 'Super Admin' || r.Staff_ID === state.me;

	/* ---------------------------------------------- render */
	function rowHtml(r) {
		return `
		<tr data-id="${r.Staff_ID}">
			<td><div class="who">
				<span class="avatar avatar-sm">${esc(initials(r.Name))}</span>
				<div><strong>${esc(r.Name)}${r.Staff_ID === state.me ? ' <span class="tiny">(you)</span>' : ''}</strong>
				<small>${esc(r.Email || 'no email')}${r.Plate_number ? ' · ' + esc(r.Plate_number) : ''}</small></div>
			</div></td>
			<td><span class="pill ${ROLE_CLASS[r.Role]}">${esc(r.Role)}</span></td>
			<td><span class="pill ${STATUS_CLASS[r.Status]}"><span class="dot"></span>${esc(r.Status)}</span></td>
			<td class="num">${esc(r.Phone || '—')}</td>
			<td class="num">${fmt(r.Created_at)}</td>
			<td class="num">${ago(r.Last_login_at)}</td>
			<td style="text-align:right">
				${locked(r)
					? `<span class="icon-btn" title="${r.Staff_ID === state.me ? 'This is you' : 'Super Admins are protected'}" style="cursor:default">${icon('shield')}</span>`
					: `<button class="icon-btn" data-menu-trigger aria-haspopup="menu" aria-label="Actions for ${esc(r.Name)}">${icon('dots')}</button>`}
			</td>
		</tr>`;
	}

	function render(animate) {
		tbody.innerHTML = state.rows.length
			? state.rows.map(rowHtml).join('')
			: '<tr><td colspan="7"><div class="empty">No staff match these filters.</div></td></tr>';
		if (animate) CD.stagger(tbody.children);
	}

	function replaceRow(r) {
		const i = state.rows.findIndex((x) => x.Staff_ID === r.Staff_ID);
		if (i < 0) return;
		state.rows[i] = r;
		const old = tbody.querySelector(`tr[data-id="${r.Staff_ID}"]`);
		const tmp = document.createElement('tbody');
		tmp.innerHTML = rowHtml(r);
		const fresh = tmp.firstElementChild;
		fresh.classList.add('is-flash');
		old.replaceWith(fresh);
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
			render(animate);
			const start = data.total ? (data.page - 1) * data.per_page + 1 : 0;
			summary.textContent = `${start}–${Math.min(data.total, data.page * data.per_page)} of ${data.total} staff`;
			document.querySelector('[data-page="prev"]').disabled = data.page <= 1;
			document.querySelector('[data-page="next"]').disabled = data.page >= data.pages;
			Object.entries(data.counts).forEach(([role, n]) => {
				const el = document.querySelector(`[data-role-count="${role}"]`);
				if (el && el.textContent !== String(n)) CD.countUp(el, n, { duration: 0.8 });
			});
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

	/* ---------------------------------------------- add / edit form */
	function syncCourierField() {
		const isCourier = form.elements.action.value === 'create' ? form.elements.Role.value === 'Courier' : form.dataset.role === 'Courier';
		form.querySelector('[data-courier-only]').hidden = !isCourier;
	}
	form.elements.Role.addEventListener('change', syncCourierField);

	function openForm(r) {
		CD.clearErrors(form);
		form.reset();
		const editing = !!r;
		form.elements.action.value = editing ? 'update' : 'create';
		form.elements.Staff_ID.value = editing ? r.Staff_ID : '';
		form.dataset.role = editing ? r.Role : '';
		form.querySelectorAll('[data-create-only]').forEach((el) => (el.hidden = editing));
		document.getElementById('staff-title').textContent = editing ? `Edit ${r.Name}` : 'Add staff';
		document.getElementById('staff-sub').textContent = editing ? `${r.Role} · use the row menu to change role or access.` : 'They sign in with their work email.';
		if (editing) CD.fillForm(form, r);
		form.elements.Password.dispatchEvent(new Event('input'));
		syncCourierField();
		modal.open('staff-modal');
	}

	function showSecret(name, password) {
		document.getElementById('secret-sub').textContent = `For ${name}. They can sign in with it right away.`;
		document.getElementById('secret-value').textContent = password;
		modal.open('secret-modal');
	}
	document.getElementById('secret-copy').addEventListener('click', () => CD.copy(document.getElementById('secret-value').textContent));
	document.getElementById('secret-modal').addEventListener('modal:closed', () => (document.getElementById('secret-value').textContent = ''));

	CD.ajaxForm(form, API, {
		onSuccess: async (data) => {
			const creating = form.elements.action.value === 'create';
			modal.close('staff-modal');
			if (creating) {
				state.page = 1;
				filters.reset();
				await load();
				toast(`${data.row.Name} added as ${data.row.Role}`);
				if (data.temp_password) setTimeout(() => showSecret(data.row.Name, data.temp_password), 300);
			} else {
				replaceRow(data.row);
				toast('Details saved');
			}
		},
	});
	document.querySelector('[data-staff-new]').addEventListener('click', () => openForm(null));

	/* ---------------------------------------------- row actions */
	async function run(r, payload, success) {
		try {
			const data = await api(API, { Staff_ID: r.Staff_ID, ...payload });
			replaceRow(data.row);
			toast(success(data.row));
			return data;
		} catch (err) {
			toast(err.message, 'error');
		}
	}

	function openMenu(trigger, r) {
		const items = [
			{ label: 'Edit details', icon: 'edit', onClick: () => openForm(r) },
			{ sep: true },
			{ heading: 'Change role' },
			...ASSIGNABLE.filter((role) => role !== r.Role).map((role) => ({
				label: `Make ${role}`,
				icon: 'user',
				onClick: () => run(r, { action: 'set_role', Role: role }, (row) => `${row.Name} is now ${row.Role}`),
			})),
			{ sep: true },
			{
				label: 'Reset password',
				icon: 'key',
				onClick: async () => {
					const ok = await CD.confirm({ title: `Reset ${esc(r.Name)}'s password?`, body: 'Their current password stops working immediately.', confirmText: 'Reset password' });
					if (!ok) return;
					const data = await run(r, { action: 'reset_password' }, () => 'Password reset');
					if (data) showSecret(r.Name, data.temp_password);
				},
			},
		];
		if (r.Status === 'Active') {
			items.push({
				label: 'Suspend access',
				icon: 'ban',
				danger: true,
				onClick: async () => {
					const ok = await CD.confirm({ title: `Suspend ${esc(r.Name)}?`, body: 'They are signed out on their next click and cannot sign in until reactivated.', confirmText: 'Suspend', danger: true });
					if (ok) run(r, { action: 'set_status', Status: 'Suspended' }, (row) => `${row.Name} suspended`);
				},
			});
		} else {
			items.push({ label: 'Reactivate', icon: 'check', onClick: () => run(r, { action: 'set_status', Status: 'Active' }, (row) => `${row.Name} is active again`) });
		}
		if (r.Status !== 'Deactivated') {
			items.push({
				label: 'Deactivate account',
				icon: 'power',
				danger: true,
				onClick: async () => {
					const ok = await CD.confirm({ title: `Deactivate ${esc(r.Name)}?`, body: 'Use this when someone leaves. The record is kept and can be reactivated later.', confirmText: 'Deactivate', danger: true });
					if (ok) run(r, { action: 'set_status', Status: 'Deactivated' }, (row) => `${row.Name} deactivated`);
				},
			});
		}
		CD.menu(trigger, items);
	}

	tbody.addEventListener('click', (ev) => {
		const trigger = ev.target.closest('[data-menu-trigger]');
		if (!trigger) return;
		const r = state.rows.find((x) => String(x.Staff_ID) === trigger.closest('tr').dataset.id);
		if (r) openMenu(trigger, r);
	});

	load();
})();
