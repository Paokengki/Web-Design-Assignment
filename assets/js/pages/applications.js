/* Café applications — staff review: search/filter/paging, approve (creates café + kitchen account), reject with reason. */
(function () {
	'use strict';
	const { api, esc, toast, modal } = CD;
	const API = 'api/admin/applications.php';

	const filters = document.getElementById('app-filters');
	const tbody = document.querySelector('#app-table tbody');
	const summary = document.getElementById('app-summary');
	const rejectForm = document.getElementById('reject-form');

	const STATUS_CLASS = { Pending: 'pill-warn', Approved: 'pill-ok', Rejected: 'pill-bad' };

	let state = { page: 1, pages: 1, rows: [] };
	let requestId = 0;

	const fmt = (s) => (s ? new Date(s.replace(' ', 'T')).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' }) : '—');

	/* ---------------------------------------------- render */
	function actionsHtml(r) {
		if (r.Status === 'Pending') {
			return `<div class="row-actions" style="display:flex;gap:6px;justify-content:flex-end">
				<button class="btn btn-primary btn-sm" data-approve>Approve</button>
				<button class="btn btn-ghost btn-sm" data-reject>Reject</button>
			</div>`;
		}
		const note = r.Status === 'Rejected' && r.Reject_reason ? ` · ${esc(r.Reject_reason)}` : '';
		return `<span class="tiny">${fmt(r.Reviewed_At)}${note}</span>`;
	}

	function rowHtml(r) {
		return `
		<tr data-id="${r.Application_ID}">
			<td><strong>${esc(r.Name)}</strong><br><small class="tiny">${esc(r.Restaurant_type)}</small></td>
			<td>${esc(r.Owner_Name)}</td>
			<td>${esc(r.Email)}<br><small class="tiny">${esc(r.Contain_number)}</small></td>
			<td>${esc(r.Address)}</td>
			<td class="num">${fmt(r.Created_At)}</td>
			<td><span class="pill ${STATUS_CLASS[r.Status]}"><span class="dot"></span>${esc(r.Status)}</span></td>
			<td style="text-align:right">${actionsHtml(r)}</td>
		</tr>`;
	}

	function render(animate) {
		tbody.innerHTML = state.rows.length
			? state.rows.map(rowHtml).join('')
			: '<tr><td colspan="7"><div class="empty">No applications match these filters.</div></td></tr>';
		if (animate) CD.stagger(tbody.children);
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
			summary.textContent = `${start}–${Math.min(data.total, data.page * data.per_page)} of ${data.total} applications`;
			document.querySelector('[data-page="prev"]').disabled = data.page <= 1;
			document.querySelector('[data-page="next"]').disabled = data.page >= data.pages;
			Object.entries(data.counts).forEach(([status, n]) => {
				const el = document.querySelector(`[data-count-status="${status}"]`);
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

	/* ---------------------------------------------- approve */
	function showSecret(name, email, password) {
		document.getElementById('secret-sub').textContent = `Sign-in for ${name}: ${email}. They can use this password right away.`;
		document.getElementById('secret-value').textContent = password;
		modal.open('secret-modal');
	}
	document.getElementById('secret-copy').addEventListener('click', () => CD.copy(document.getElementById('secret-value').textContent));
	document.getElementById('secret-modal').addEventListener('modal:closed', () => (document.getElementById('secret-value').textContent = ''));

	async function approve(r) {
		const ok = await CD.confirm({
			title: `Approve ${esc(r.Name)}?`,
			body: `This creates the café and a kitchen staff account for ${esc(r.Owner_Name)}, and emails the sign-in details to ${esc(r.Email)}. Add the café photo and menu afterwards in Cafés & menu.`,
			confirmText: 'Approve',
		});
		if (!ok) return;
		try {
			const data = await api(API, { action: 'approve', Application_ID: r.Application_ID });
			await load(false);
			if (data.emailed) {
				toast(`${r.Name} approved — sign-in details emailed to ${data.staff_email}`);
			} else {
				// Email failed: the café is still approved, so show the password to pass on by hand.
				toast(`${r.Name} approved, but the email could not be sent`, 'error');
				setTimeout(() => showSecret(r.Name, data.staff_email, data.temp_password), 300);
			}
		} catch (err) {
			toast(err.message, 'error');
		}
	}

	/* ---------------------------------------------- reject */
	function openReject(r) {
		CD.clearErrors(rejectForm);
		rejectForm.reset();
		rejectForm.elements.Application_ID.value = r.Application_ID;
		document.getElementById('reject-sub').textContent = `${r.Name} · ${r.Owner_Name}`;
		modal.open('reject-modal');
	}

	CD.ajaxForm(rejectForm, API, {
		onSuccess: async (data) => {
			modal.close('reject-modal');
			await load(false);
			toast('Application rejected');
		},
	});

	/* ---------------------------------------------- row buttons */
	tbody.addEventListener('click', (ev) => {
		const tr = ev.target.closest('tr[data-id]');
		if (!tr) return;
		const r = state.rows.find((x) => String(x.Application_ID) === tr.dataset.id);
		if (!r) return;
		if (ev.target.closest('[data-approve]')) approve(r);
		else if (ev.target.closest('[data-reject]')) openReject(r);
	});

	load();
})();
