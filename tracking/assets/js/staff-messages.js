/**
 * Messages inbox — staff (tracking/messages.php) and customers (customer/messages.php).
 * <div id="inbox" data-as="staff|customer">. Polls the thread list and the open
 * conversation every 2 s via tracking/api/chat.php.
 */
(function () {
	'use strict';
	const root = document.getElementById('inbox');
	if (!root || !window.CD) return;

	const API = 'tracking/api/chat.php';
	const AS = root.dataset.as === 'customer' ? 'customer' : 'staff';
	const isMine = (sender) => (AS === 'customer' ? sender === 'customer' : sender !== 'customer');
	const POLL_MS = 2000;
	const esc = CD.esc;
	const listEl = document.getElementById('inbox-threads');
	const log = document.getElementById('inbox-log');
	const title = document.getElementById('inbox-title');
	const sub = document.getElementById('inbox-sub');
	const form = document.getElementById('inbox-form');
	const input = form.querySelector('input');
	const sendBtn = form.querySelector('button');

	const PARTY = { restaurant: 'Restaurant', driver: 'Driver', admin: 'Support' };
	let threads = [];
	let filter = '';
	let current = null; // { id, since, messages, canReply }
	let timer = null;

	const when = (iso) => {
		if (!iso) return '';
		const d = new Date(iso);
		return d.toDateString() === new Date().toDateString()
			? d.toLocaleTimeString('en-MY', { hour: 'numeric', minute: '2-digit' })
			: d.toLocaleDateString('en-MY', { day: 'numeric', month: 'short' });
	};

	function renderList() {
		if (!threads.length) {
			listEl.innerHTML = '<p class="inbox-empty">No conversations yet.</p>';
			return;
		}
		listEl.innerHTML = threads.map((t) => {
			const preview = (t.last_sender && isMine(t.last_sender) ? 'You: ' : (AS === 'staff' && t.last_sender !== 'customer' ? (PARTY[t.last_sender] || t.last_sender) + ': ' : '')) + (t.last || '');
			return '<button type="button" class="inbox-thread' + (current && current.id === t.id ? ' is-active' : '') + '" data-id="' + t.id + '">'
				+ '<strong>' + esc(t.customer) + ' · #' + t.payment_id + '</strong>'
				+ '<time>' + esc(when(t.at)) + (t.unread ? ' <span class="chat-badge">' + (t.unread > 9 ? '9+' : t.unread) + '</span>' : '') + '</time>'
				+ '<p>' + esc(preview) + '</p>'
				+ '<span class="tag">' + esc(PARTY[t.party]) + ' · ' + esc(t.with) + (t.status ? ' · ' + esc(t.status) : '') + '</span>'
				+ '</button>';
		}).join('');
	}

	function bubble(m) {
		const mine = isMine(m.sender);
		const who = mine && AS === 'customer' ? 'You' : (m.sender === 'customer' ? 'Customer' : (m.name || PARTY[m.sender] || m.sender));
		return '<div class="chat-msg ' + (mine ? 'is-mine' : 'is-theirs') + (m.auto ? ' is-auto' : '') + '">'
			+ '<div class="chat-bubble">' + esc(m.body) + '</div>'
			+ '<small>' + esc(who) + (m.auto ? ' · automatic' : '') + ' · ' + esc(when(m.at)) + '</small></div>';
	}

	function renderConversation(scroll) {
		if (!current) return;
		const near = log.scrollHeight - log.scrollTop - log.clientHeight < 60;
		log.innerHTML = current.messages.length ? current.messages.map(bubble).join('') : '<p class="inbox-empty">No messages yet.</p>';
		if (scroll || near) log.scrollTop = log.scrollHeight;
		input.disabled = sendBtn.disabled = !current.canReply;
		input.placeholder = current.canReply ? (AS === 'customer' ? 'Write a message…' : 'Reply to the customer…') : 'Read-only — only the ' + (PARTY[current.party] || '').toLowerCase() + ' can reply here';
	}

	async function loadThreads() {
		try {
			const data = await CD.api(API, { as: AS, action: 'threads', party: filter });
			threads = data.threads || [];
			renderList();
		} catch (e) { /* keep polling */ }
	}

	async function loadMessages(scroll) {
		if (!current) return;
		const id = current.id;
		try {
			const data = await CD.api(API, { as: AS, action: 'messages', thread_id: id, since: current.since });
			if (!current || current.id !== id) return;
			current.canReply = data.can_reply;
			if (data.messages.length) {
				current.messages = current.messages.concat(data.messages);
				current.since = data.messages[data.messages.length - 1].id;
				renderConversation(scroll);
			} else if (scroll) {
				renderConversation(true);
			}
		} catch (e) { /* keep polling */ }
	}

	async function openThread(id) {
		const t = threads.find((x) => x.id === id);
		if (!t) return;
		current = { id, party: t.party, since: 0, messages: [], canReply: t.can_reply };
		title.textContent = t.customer + ' · Order #' + t.payment_id;
		if (AS === 'customer') {
			sub.innerHTML = esc(PARTY[t.party] + ' chat' + (t.status ? ' · ' + t.status : '')) + ' · <a class="grad-text" href="' + CD.url('tracking/customer/track.php?id=' + t.payment_id) + '">Track order</a>';
		} else {
			sub.textContent = PARTY[t.party] + ' chat · ' + t.with + (t.status ? ' · ' + t.status : '');
		}
		log.innerHTML = '<p class="inbox-empty">Loading…</p>';
		t.unread = 0;
		renderList();
		await loadMessages(true);
		if (current.canReply) input.focus();
	}

	listEl.addEventListener('click', (ev) => {
		const b = ev.target.closest('[data-id]');
		if (b) openThread(+b.dataset.id);
	});

	root.querySelectorAll('[data-filter]').forEach((b) => b.addEventListener('click', () => {
		filter = b.dataset.filter;
		root.querySelectorAll('[data-filter]').forEach((x) => x.classList.toggle('is-active', x === b));
		loadThreads();
	}));

	form.addEventListener('submit', async (ev) => {
		ev.preventDefault();
		const body = input.value.trim();
		if (!body || !current || !current.canReply) return;
		sendBtn.disabled = true;
		try {
			await CD.api(API, { as: AS, action: 'send', thread_id: current.id, body });
			input.value = '';
			await loadMessages(true);
			loadThreads();
		} catch (err) {
			CD.toast(err.message, 'error');
		} finally {
			sendBtn.disabled = !(current && current.canReply);
			input.focus();
		}
	});

	function schedule() {
		clearTimeout(timer);
		timer = setTimeout(async () => {
			if (!document.hidden) await Promise.all([loadThreads(), loadMessages(false)]);
			document.dispatchEvent(new Event('cd:chat-read')); // refresh the navbar badge
			schedule();
		}, POLL_MS);
	}

	// open a chat directly: messages.php?thread=ID
	const wanted = +new URLSearchParams(location.search).get('thread');
	loadThreads().then(() => { if (wanted) openThread(wanted); });
	schedule();
})();
