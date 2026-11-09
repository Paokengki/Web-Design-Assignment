/* CafeDash core/motion.js — GSAP reveals, page transitions, magnetic buttons, counters. */
(function () {
	'use strict';
	const CD = window.CD;
	const reduced = CD.reduced;
	const hasGsap = typeof window.gsap !== 'undefined';

	/* ------------------------------------------------ motion */
	CD.reveal = (root = document) => {
		const els = root.querySelectorAll('[data-reveal]:not(.is-revealed)');
		els.forEach((el) => el.classList.add('is-revealed'));
		if (!hasGsap || reduced || !els.length) return;
		if (window.ScrollTrigger) {
			gsap.set(els, { y: 28, opacity: 0 });
			ScrollTrigger.batch(els, {
				start: 'top 92%',
				once: true,
				onEnter: (batch) => gsap.to(batch, { y: 0, opacity: 1, duration: 0.9, ease: 'expo.out', stagger: 0.08, clearProps: 'transform' }),
			});
		}
	};

	CD.stagger = (els) => {
		if (!hasGsap || reduced || !els || !els.length) return;
		gsap.fromTo(els, { y: 16, opacity: 0 }, { y: 0, opacity: 1, duration: 0.6, ease: 'expo.out', stagger: 0.04, clearProps: 'transform,opacity' });
	};

	CD.countUp = (el, to, opts = {}) => {
		const decimals = opts.decimals || 0;
		const fmt = (v) => (opts.prefix || '') + Number(v).toLocaleString('en-MY', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
		if (!hasGsap || reduced) return (el.textContent = fmt(to));
		const o = { v: 0 };
		gsap.to(o, { v: to, duration: opts.duration || 1.6, ease: 'expo.out', onUpdate: () => (el.textContent = fmt(o.v)) });
	};

	function initMotion() {
		// page curtain: fade in on load
		const curtain = document.querySelector('.page-curtain');
		if (curtain) {
			if (hasGsap && !reduced) gsap.to(curtain, { opacity: 0, duration: 0.6, ease: 'power2.out', onComplete: () => (curtain.style.display = 'none') });
			else curtain.style.display = 'none';
		}

		// page curtain: fade out when leaving to another internal page
		document.addEventListener('click', (ev) => {
			const a = ev.target.closest('a[href]');
			if (!a || ev.defaultPrevented || ev.metaKey || ev.ctrlKey || ev.shiftKey || a.target || a.hasAttribute('download') || a.dataset.noTransition !== undefined) return;
			const url = new URL(a.href, location.href);
			if (url.origin !== location.origin || (url.pathname === location.pathname && url.hash)) return;
			if (!hasGsap || reduced || !curtain) return;
			ev.preventDefault();
			curtain.style.display = 'block';
			gsap.fromTo(curtain, { opacity: 0 }, { opacity: 1, duration: 0.35, ease: 'power2.in', onComplete: () => (location.href = a.href) });
		});
		window.addEventListener('pageshow', (ev) => {
			if (ev.persisted && curtain) curtain.style.display = 'none';
		});

		CD.reveal();
		document.querySelectorAll('[data-count]').forEach((el) =>
			CD.countUp(el, parseFloat(el.dataset.count) || 0, { decimals: +el.dataset.decimals || 0, prefix: el.dataset.prefix || '' })
		);

		if (!hasGsap || reduced) return;

		// magnetic buttons
		document.querySelectorAll('[data-magnetic]').forEach((el) => {
			el.addEventListener('mousemove', (ev) => {
				const r = el.getBoundingClientRect();
				gsap.to(el, { x: (ev.clientX - r.left - r.width / 2) * 0.25, y: (ev.clientY - r.top - r.height / 2) * 0.35, duration: 0.4, ease: 'power3.out' });
			});
			el.addEventListener('mouseleave', () => gsap.to(el, { x: 0, y: 0, duration: 0.6, ease: 'elastic.out(1, .4)' }));
		});
	}

	// cursor glow on cards (event delegation, works for cards rendered later)
	document.addEventListener('pointermove', (ev) => {
		const card = ev.target.closest && ev.target.closest('.card-glow');
		if (!card) return;
		const r = card.getBoundingClientRect();
		card.style.setProperty('--mx', ev.clientX - r.left + 'px');
		card.style.setProperty('--my', ev.clientY - r.top + 'px');
	});

	document.addEventListener('DOMContentLoaded', initMotion);
})();
