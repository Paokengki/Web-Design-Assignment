/* Order confirmation: tick draws in, a small burst of crema-coloured confetti. */
(function () {
	'use strict';
	if (!window.gsap || CD.reduced) return;
	const mark = document.querySelector('.success-mark');
	gsap.from(mark, { scale: 0, rotate: -90, duration: 0.9, ease: 'back.out(2)', delay: 0.2 });
	const colors = ['#ffcb6b', '#f5a524', '#c46cf2', '#8b5cf6', '#22d3ee'];
	const r = mark.getBoundingClientRect();
	for (let i = 0; i < 36; i++) {
		const dot = document.createElement('i');
		dot.className = 'confetti';
		dot.style.background = colors[i % colors.length];
		dot.style.left = r.left + r.width / 2 + 'px';
		dot.style.top = r.top + r.height / 2 + window.scrollY + 'px';
		document.body.appendChild(dot);
		const a = Math.random() * Math.PI * 2;
		const d = 80 + Math.random() * 180;
		gsap.to(dot, {
			x: Math.cos(a) * d,
			y: Math.sin(a) * d + 120,
			rotate: Math.random() * 720,
			opacity: 0,
			duration: 1.4 + Math.random(),
			ease: 'power3.out',
			delay: 0.35,
			onComplete: () => dot.remove(),
		});
	}
})();
