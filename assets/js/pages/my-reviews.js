/* My reviews (customer): rate delivered orders, edit within the window, read admin replies. */
(function () {
	'use strict';
	const { api, esc, toast, modal } = CD;
	const API = 'api/customer/reviews.php';

	const form = document.getElementById('review-form');
	const panels = { 'to-review': document.getElementById('panel-to-review'), mine: document.getElementById('panel-mine') };
	const STATUS_CLASS = { Pending: 'pill-warn', Investigating: 'pill-amber', Resolved: 'pill-ok' };

	let state = { to_review: [], mine: [], edit_hours: 24 };
	let activeTab = 'to-review';

	/* ---------------------------------------------- helpers */
	const stars = (n) => `<span class="stars" title="${n} out of 5">${'★'.repeat(n)}<span class="off">${'★'.repeat(5 - n)}</span></span>`;
	const money = (v) => 'RM' + Number(v).toFixed(2);
	const fmt = (s) => (s ? new Date(s.replace(' ', 'T')).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' }) : '—');
	const left = (m) => (m >= 60 ? `${Math.floor(m / 60)}h ${m % 60}m` : `${m}m`);
	const clip = (s, n) => (s && s.length > n ? s.slice(0, n - 1) + '…' : s || '');

	/* ---------------------------------------------- render */
	function toReviewCard(o) {
		return `
		<article class="glass card rv-card" data-pay="${o.Payment_ID}">
			<div class="rv-head"><h3>${esc(o.cafe_name)}</h3><strong>${money(o.Payment_amount)}</strong></div>
			<p class="rv-meta">Order #${o.Payment_ID} · delivered ${fmt(o.Delivered_at)}</p>
			<p class="rv-comment">${esc(clip(o.items, 140))}</p>
			<div class="rv-actions"><button class="btn btn-primary btn-sm" data-rate>Rate this order</button></div>
		</article>`;
	}

	function myCard(r) {
		const hidden = Number(r.Is_hidden) === 1;
		const canEdit = Number(r.can_edit) === 1;
		return `
		<article class="glass card rv-card" data-review="${r.Review_ID}">
			<div class="rv-head"><h3>${esc(r.cafe_name)}</h3>${stars(Number(r.Rating))}</div>
			<p class="rv-meta">Order #${r.Payment_ID} · ${fmt(r.Created_at)}${r.Edited_at ? ' · edited' : ''}
				${hidden ? '' : ` · <span class="pill ${STATUS_CLASS[r.Issue_status]}">${esc(r.Issue_status)}</span>`}</p>
			<p class="rv-comment">${esc(r.Comment)}</p>
			${hidden ? '<p class="rv-note">This review was hidden by our team.</p>' : ''}
			${r.Admin_reply ? `<div class="rv-reply"><strong>Reply from CafeDash · ${fmt(r.Replied_at)}</strong><p class="rv-comment">${esc(r.Admin_reply)}</p></div>` : ''}
			<div class="rv-actions">
				${canEdit ? `<button class="btn btn-ghost btn-sm" data-edit>Edit review</button><span class="tiny">${left(Number(r.minutes_left))} left to edit</span>`
					: (hidden ? '' : `<span class="tiny">Editing closed after ${state.edit_hours} hours</span>`)}
			</div>
		</article>`;
	}

	function render() {
		panels['to-review'].innerHTML = state.to_review.length
			? state.to_review.map(toReviewCard).join('')
			: '<div class="empty">Nothing to review right now. Delivered orders will show up here.</div>';
		panels.mine.innerHTML = state.mine.length
			? state.mine.map(myCard).join('')
			: '<div class="empty">You haven’t reviewed any orders yet.</div>';
		document.getElementById('count-to-review').textContent = state.to_review.length ? `(${state.to_review.length})` : '';
		document.getElementById('count-mine').textContent = state.mine.length ? `(${state.mine.length})` : '';
		CD.stagger(panels[activeTab].children);
	}

	async function load() {
		try {
			Object.assign(state, await api(API, { action: 'list' }));
			render();
		} catch (err) {
			toast(err.message, 'error');
		}
	}

	/* ---------------------------------------------- tabs */
	function showTab(name) {
		activeTab = name;
		document.querySelectorAll('[data-tab]').forEach((b) => {
			const on = b.dataset.tab === name;
			b.classList.toggle('is-active', on);
			b.setAttribute('aria-selected', String(on));
		});
		Object.entries(panels).forEach(([k, el]) => (el.hidden = k !== name));
		CD.stagger(panels[name].children);
	}
	document.querySelectorAll('[data-tab]').forEach((b) => b.addEventListener('click', () => showTab(b.dataset.tab)));

	/* ---------------------------------------------- rate / edit form */
	const counter = document.getElementById('rv-count');
	const updateCount = () => (counter.textContent = `${form.elements.Comment.value.length} / 1000`);
	form.elements.Comment.addEventListener('input', updateCount);

	function openForm(r, o) {
		CD.clearErrors(form);
		form.reset();
		const editing = !!r;
		form.elements.action.value = editing ? 'update' : 'create';
		form.elements.Payment_ID.value = editing ? '' : o.Payment_ID;
		form.elements.Review_ID.value = editing ? r.Review_ID : '';
		document.getElementById('review-title').textContent = editing ? 'Edit your review' : 'Rate your order';
		document.getElementById('review-sub').textContent = editing ? `${r.cafe_name} · order #${r.Payment_ID}` : `${o.cafe_name} · order #${o.Payment_ID}`;
		form.querySelector('[type="submit"]').textContent = editing ? 'Save changes' : 'Submit review';
		if (editing) {
			const radio = form.querySelector(`input[name="Rating"][value="${r.Rating}"]`);
			if (radio) radio.checked = true;
			form.elements.Comment.value = r.Comment;
		}
		updateCount();
		modal.open('review-modal');
	}

	CD.ajaxForm(form, API, {
		onSuccess: async () => {
			const editing = form.elements.action.value === 'update';
			modal.close('review-modal');
			await load();
			showTab('mine');
			toast(editing ? 'Review updated' : 'Thanks for your review!');
		},
	});

	/* ---------------------------------------------- buttons */
	panels['to-review'].addEventListener('click', (ev) => {
		if (!ev.target.closest('[data-rate]')) return;
		const o = state.to_review.find((x) => String(x.Payment_ID) === ev.target.closest('[data-pay]').dataset.pay);
		if (o) openForm(null, o);
	});
	panels.mine.addEventListener('click', (ev) => {
		if (!ev.target.closest('[data-edit]')) return;
		const r = state.mine.find((x) => String(x.Review_ID) === ev.target.closest('[data-review]').dataset.review);
		if (r) openForm(r, null);
	});

	/* ---------------------------------------------- deep link from the Orders page: reviews.php?rate=ORDER_ID */
	function handleDeepLink() {
		const wanted = new URLSearchParams(location.search).get('rate');
		if (!wanted) return;
		history.replaceState(null, '', location.pathname); // keep the URL clean so a refresh doesn't reopen it
		const o = state.to_review.find((x) => String(x.Payment_ID) === wanted);
		if (o) openForm(null, o);
		else showTab('mine'); // already reviewed (or not eligible): show the reviews instead
	}

	load().then(handleDeepLink);
})();
