/* CafeDash core/api.js — creates window.CD, URL + escaping helpers, and CD.api() for JSON endpoints. Load first. */
(function () {
	'use strict';

	const meta = (n) => document.querySelector(`meta[name="${n}"]`)?.content || '';
	const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	const hasGsap = typeof window.gsap !== 'undefined';
	if (hasGsap && window.ScrollTrigger) gsap.registerPlugin(ScrollTrigger);

	const CD = (window.CD = {
		base: meta('app-base').replace(/\/$/, ''),
		csrf: meta('csrf-token'),
		reduced,
	});

	CD.url = (p) => CD.base + '/' + String(p).replace(/^\//, '');

	CD.esc = (v) =>
		String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

	/* ------------------------------------------------ fetch */
	/** POST to a PHP endpoint. Returns `data`, throws Error(message) when ok === false. */
	CD.api = async (endpoint, payload = {}) => {
		const body = payload instanceof FormData ? payload : new FormData();
		if (!(payload instanceof FormData)) {
			Object.entries(payload).forEach(([k, v]) => body.append(k, v ?? ''));
		}
		let res;
		try {
			res = await fetch(CD.url(endpoint), {
				method: 'POST',
				body,
				credentials: 'same-origin',
				headers: { 'X-CSRF-Token': CD.csrf, 'X-Requested-With': 'fetch', Accept: 'application/json' },
			});
		} catch (e) {
			throw new Error('Network error. Check your connection.');
		}
		let json;
		try {
			json = await res.json();
		} catch (e) {
			throw new Error(`Unexpected server response (${res.status}).`);
		}
		if (res.status === 401) setTimeout(() => location.reload(), 1200);
		if (!json.ok) {
			const err = new Error(json.error || 'Something went wrong.');
			err.status = res.status;
			err.fields = json.data && json.data.fields;
			throw err;
		}
		// Attach the server message to the returned data if it's an object.
		if (json.data && typeof json.data === 'object' && !Array.isArray(json.data)) {
			json.data._message = json.error || '';
		}
		return json.data;
	};

})();
