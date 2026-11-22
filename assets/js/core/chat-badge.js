/* CafeDash core/chat-badge.js — unread-message badge on the navbar chat button (customers)
   and on the sidebar "Messages" link (staff). <body data-chat="customer|staff"> turns it on. */
(function () {
	'use strict';
	const mode = document.body.dataset.chat;
	const badges = [...document.querySelectorAll('[data-chat-count]')];
	if (!mode || !badges.length || !window.CD) return;

	const POLL_MS = 10000;
	let timer = null;
	let last = -1;

	function show(n) {
		if (n === last) return;
		last = n;
		badges.forEach((b) => {
			b.hidden = n < 1;
			b.textContent = n > 9 ? '9+' : String(n);
			const link = b.closest('a');
			if (link) link.setAttribute('aria-label', 'Messages' + (n ? ` (${n} unread)` : ''));
		});
	}

	async function refresh() {
		try {
			const d = await CD.api('tracking/api/chat.php', { as: mode, action: 'unread' });
			show(d.count || 0);
		} catch (e) { /* signed out or offline: leave the badge as it is */ }
	}

	function schedule() {
		clearTimeout(timer);
		timer = setTimeout(async () => {
			if (!document.hidden) await refresh();
			schedule();
		}, POLL_MS);
	}

	// chat pages fire this after marking messages read, so the badge drops straight away
	document.addEventListener('cd:chat-read', refresh);
	document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
	refresh();
	schedule();
})();
