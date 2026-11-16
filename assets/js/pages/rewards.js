/* Dashboard "Rewards": spend loyalty points on vouchers, see the ones you own.
 * Data: api/customer/rewards.php (list | redeem). First paint comes from JSON embedded in the page. */
(function () {
	'use strict';
	const { api, esc, icon, toast } = CD;
	const API = 'api/customer/rewards.php';
	const shop = document.getElementById('reward-shop');
	const mine = document.getElementById('reward-mine');
	if (!shop) return;

	const money = (n) => 'RM' + (+n).toFixed(2);
	const pctText = (n) => (+n % 1 ? (+n).toFixed(1) : +n);
	const date = (iso) => new Date(iso).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' });
	function endsIn(iso) {
		const days = Math.ceil((new Date(iso) - Date.now()) / 86400000);
		return days <= 1 ? 'Ends today' : days <= 14 ? `Ends in ${days} days` : `Until ${date(iso)}`;
	}
	let state = JSON.parse(document.getElementById('reward-data').textContent);

	/* ---------------------------------------------- cards */
	function rewardCard(r) {
		const facts = [r.min_spend > 0 ? `Min. spend ${money(r.min_spend)}` : 'No minimum spend', endsIn(r.expires)];
		if (r.left !== null && r.left <= 50) facts.push(r.left ? `${r.left} left` : 'All claimed');
		if (r.limit > 1 || r.mine) facts.push(`${r.mine} of ${r.limit} redeemed`);
		return `
		<article class="reward ${r.can_redeem ? '' : 'is-locked'} ${+r.percent >= 100 ? 'is-free' : ''}" data-id="${r.id}">
			<div class="reward-stub"><b>${+r.percent >= 100 ? 'FREE' : pctText(r.percent) + '%'}</b><small>${+r.percent >= 100 ? 'ORDER' : 'OFF'}</small></div>
			<div class="reward-body">
				<h4>${esc(r.title)}</h4>
				<p>${esc(r.detail || '')}</p>
				<small>${esc(facts.join(' · '))}</small>
				${r.can_redeem || r.reason !== null && !/^Need/.test(r.reason) ? '' : `<div class="reward-progress" title="${r.progress}% of the points needed"><i style="width:${r.progress}%"></i></div>`}
				<div class="reward-foot">
					<span class="reward-cost">${icon('star')}${r.cost.toLocaleString()} pts</span>
					${r.can_redeem
						? `<button type="button" class="btn btn-primary btn-sm" data-redeem>Redeem</button>`
						: `<span class="reward-reason">${icon('lock')}${esc(r.reason)}</span>`}
				</div>
			</div>
		</article>`;
	}

	const STATUS = {
		ready: ['pill-ok', 'Ready to use'],
		used: ['pill-muted', 'Used'],
		expired: ['pill-bad', 'Expired'],
		paused: ['pill-warn', 'Paused'],
	};
	function ownedCard(v) {
		const [pill, label] = STATUS[v.status];
		return `
		<article class="reward is-owned is-${v.status}">
			<div class="reward-stub"><b>${+v.percent >= 100 ? 'FREE' : pctText(v.percent) + '%'}</b><small>${+v.percent >= 100 ? 'ORDER' : 'OFF'}</small></div>
			<div class="reward-body">
				<h4>${esc(v.title)} <span class="pill ${pill}">${label}</span></h4>
				<small class="mono">${esc(v.code)}</small>
				<small>${v.status === 'used' ? `Used on order #${v.order ?? '—'}` : v.status === 'expired' ? `Expired ${date(v.expires)}` : `${v.min_spend > 0 ? `Min. spend ${money(v.min_spend)} · ` : ''}${endsIn(v.expires)}`}</small>
				<div class="reward-foot">
					<span class="reward-cost muted">${v.points.toLocaleString()} pts · ${date(v.redeemed)}</span>
					${v.status === 'ready' ? `<a class="btn btn-glass btn-sm" href="${CD.url('customer/home.php')}">Order now</a>` : ''}
				</div>
			</div>
		</article>`;
	}

	function render(animate) {
		shop.innerHTML = state.rewards.length
			? state.rewards.map(rewardCard).join('')
			: '<div class="empty">No rewards are available right now — check back soon.</div>';
		mine.innerHTML = state.vouchers.length
			? state.vouchers.map(ownedCard).join('')
			: '<div class="empty">You haven’t redeemed any rewards yet.</div>';
		const ready = state.vouchers.filter((v) => v.status === 'ready').length;
		document.getElementById('rewards-mine-count').textContent = ready ? ready : '';
		if (animate) CD.stagger((shop.hidden ? mine : shop).children);
	}

	function showPoints(p, animate) {
		const set = (id, v) => {
			const el = document.getElementById(id);
			if (!el) return;
			if (animate && id !== 'points-earned') CD.countUp(el, v, { duration: 0.9 });
			else el.textContent = v.toLocaleString();
		};
		set('rewards-balance', p.balance);
		set('points-balance', p.balance);
		set('points-earned', p.earned);
		set('points-spent', p.spent);
	}

	/* ---------------------------------------------- tabs */
	document.querySelectorAll('[data-rewards-tab]').forEach((tab) =>
		tab.addEventListener('click', () => {
			const which = tab.dataset.rewardsTab;
			document.querySelectorAll('[data-rewards-tab]').forEach((t) => {
				t.classList.toggle('is-active', t === tab);
				t.setAttribute('aria-selected', t === tab);
			});
			shop.hidden = which !== 'shop';
			mine.hidden = which !== 'mine';
			CD.stagger((which === 'shop' ? shop : mine).children);
		})
	);

	/* ---------------------------------------------- redeem */
	shop.addEventListener('click', async (ev) => {
		const btn = ev.target.closest('[data-redeem]');
		if (!btn) return;
		const card = btn.closest('.reward');
		const r = state.rewards.find((x) => String(x.id) === card.dataset.id);
		const after = state.points.balance - r.cost;
		const ok = await CD.confirm({
			title: `Redeem ${esc(r.title)}?`,
			body: `This spends <b>${r.cost.toLocaleString()} points</b> (you’ll have ${after.toLocaleString()} left). The voucher is valid until ${date(r.expires)} and points are not refunded if it expires unused.`,
			confirmText: `Spend ${r.cost.toLocaleString()} pts`,
		});
		if (!ok) return;
		CD.busy(btn, true);
		try {
			const data = await api(API, { action: 'redeem', Promo_ID: r.id });
			state = data;
			showPoints(data.points, true);
			render(false);
			toast(`${r.title} is yours — find it under My vouchers and at checkout.`);
			// little celebration on the points chip
			if (window.gsap && !CD.reduced) gsap.fromTo('.points-chip', { scale: 1.25 }, { scale: 1, duration: 0.7, ease: 'elastic.out(1, .4)' });
			document.querySelector('[data-rewards-tab="mine"]').click();
		} catch (err) {
			toast(err.message, 'error');
			// rules may have changed (stock ran out, points spent in another tab): reload the truth
			try {
				state = await api(API, { action: 'list' });
				showPoints(state.points, false);
				render(false);
			} catch (e) { /* keep what we have */ }
		}
	});

	render(false);
})();
