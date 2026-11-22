/**
 * Order chats on the Track Order page — private 1-on-1 conversations with the
 * restaurant, the driver and CafeDash support (tracking/api/chat.php).
 * Polls every 2 s for messages newer than the last one we have.
 */
(function () {
	'use strict';
	const root = document.getElementById('order-chat');
	if (!root || !window.CD) return;

	const API = 'tracking/api/chat.php';
	const POLL_MS = 2000;
	const paymentId = root.dataset.payment;
	const log = document.getElementById('chat-log');
	const sub = document.getElementById('chat-sub');
	const form = document.getElementById('chat-form');
	const input = form.querySelector('input');
	const sendBtn = form.querySelector('button');
	const tabs = [...root.querySelectorAll('[data-party]')];

	const PLACEHOLDER = { restaurant: 'Message the restaurant…', driver: 'Message your driver…', admin: 'Message CafeDash support…' };
	const EMPTY = {
		restaurant: 'No messages yet. Ask the café about your order here.',
		driver: 'No messages yet. Say hi to your driver.',
		admin: 'Need help? Our support team will reply here.',
	};
	const state = {};
	['restaurant', 'driver', 'admin'].forEach((p) => (state[p] = { since: 0, messages: [], available: p !== 'driver', title: '', subtitle: '', unread: 0 }));
	let active = 'restaurant';
	let timer = null;
	let busy = false;

	const esc = CD.esc;
	const time = (iso) => new Date(iso).toLocaleTimeString('en-MY', { hour: 'numeric', minute: '2-digit' });

	function bubble(m) {
		const mine = m.sender === 'customer';
		const who = mine ? 'You' : (m.name || state[active].title || m.sender);
		return '<div class="chat-msg ' + (mine ? 'is-mine' : 'is-theirs') + (m.auto ? ' is-auto' : '') + '" data-id="' + m.id + '">'
			+ '<div class="chat-bubble">' + esc(m.body) + '</div>'
			+ '<small>' + esc(who) + (m.auto ? ' · automatic' : '') + ' · ' + time(m.at) + '</small></div>';
	}

	function renderLog(scroll) {
		const st = state[active];
		sub.textContent = st.available ? (st.title + (st.subtitle && st.subtitle !== st.title ? ' · ' + st.subtitle : '')) : (st.subtitle || 'Available once a driver is assigned');
		if (!st.messages.length) {
			log.innerHTML = '<p class="chat-empty">' + esc(st.available ? EMPTY[active] : 'Your driver chat opens once a driver is assigned.') + '</p>';
		} else {
			const nearBottom = log.scrollHeight - log.scrollTop - log.clientHeight < 60;
			log.innerHTML = st.messages.map(bubble).join('');
			if (scroll || nearBottom) log.scrollTop = log.scrollHeight;
		}
		input.disabled = sendBtn.disabled = !st.available;
		input.placeholder = st.available ? PLACEHOLDER[active] : 'Driver not assigned yet';
	}

	function renderTabs() {
		tabs.forEach((t) => {
			const p = t.dataset.party;
			t.classList.toggle('is-active', p === active);
			t.setAttribute('aria-selected', p === active ? 'true' : 'false');
			t.classList.toggle('is-locked', !state[p].available);
			const badge = t.querySelector('.chat-badge');
			const n = p === active ? 0 : state[p].unread;
			badge.hidden = !n;
			badge.textContent = n > 9 ? '9+' : n;
		});
	}

	function markRead(party) {
		if (!state[party].available || !state[party].unread) return;
		state[party].unread = 0;
		CD.api(API, { as: 'customer', action: 'read', payment_id: paymentId, party }).catch(() => {});
	}

	async function poll() {
		if (busy) return;
		busy = true;
		try {
			const payload = { as: 'customer', action: 'order', payment_id: paymentId };
			Object.keys(state).forEach((p) => (payload['since_' + p] = state[p].since));
			const data = await CD.api(API, payload);
			let changedActive = false;
			Object.keys(state).forEach((p) => {
				const d = data[p];
				if (!d) return;
				const st = state[p];
				if (st.available !== d.available || st.subtitle !== d.subtitle) changedActive = changedActive || p === active;
				st.available = d.available;
				st.title = d.title;
				st.subtitle = d.subtitle;
				st.unread = d.unread;
				if (d.messages.length) {
					st.messages = st.messages.concat(d.messages);
					st.since = d.messages[d.messages.length - 1].id;
					if (p === active) changedActive = true;
				}
			});
			if (changedActive || !log.dataset.ready) {
				log.dataset.ready = '1';
				renderLog(false);
			}
			if (!document.hidden) markRead(active);
			renderTabs();
		} catch (e) {
			/* keep polling quietly; a sign-out makes CD.api reload the page */
		} finally {
			busy = false;
		}
	}

	function schedule() {
		clearTimeout(timer);
		timer = setTimeout(async () => {
			if (!document.hidden) await poll();
			schedule();
		}, POLL_MS);
	}

	function open(party, focus) {
		active = party;
		renderTabs();
		renderLog(true);
		markRead(party);
		renderTabs();
		if (focus && state[party].available) input.focus();
	}

	tabs.forEach((t) => t.addEventListener('click', () => open(t.dataset.party, true)));

	// "Message" on the driver card
	document.addEventListener('click', (ev) => {
		const b = ev.target.closest('[data-chat-open]');
		if (!b) return;
		open(b.dataset.chatOpen, true);
		root.scrollIntoView({ behavior: CD.reduced ? 'auto' : 'smooth', block: 'center' });
	});

	form.addEventListener('submit', async (ev) => {
		ev.preventDefault();
		const body = input.value.trim();
		if (!body || !state[active].available) return;
		sendBtn.disabled = true;
		try {
			await CD.api(API, { as: 'customer', action: 'send', payment_id: paymentId, party: active, body });
			input.value = '';
			await poll();
			log.scrollTop = log.scrollHeight;
		} catch (err) {
			CD.toast(err.message, 'error');
		} finally {
			sendBtn.disabled = !state[active].available;
			input.focus();
		}
	});

	document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });

	poll().then(() => renderLog(true));
	schedule();
})();
