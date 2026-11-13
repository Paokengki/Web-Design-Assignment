/* CafeDash pages/mascots.js — the four auth-page mascots (Mocha, Berry, Crumb, Pearl).
 *
 * Markup: app/layout/auth_visual.php. Needs GSAP (loaded globally by the master layout) and core/api.js (CD.reduced).
 *
 * MODES — decided by which form field has focus:
 *   follow   no field focused  → pupils track the mouse (or wander on touch screens)
 *   curious  username / email  → eyebrows up, "o" mouth, lean in, pupils stare at the text caret as you type
 *   privacy  password (hidden) → eyes shut, hands over eyes, bodies lean away
 *   peek     password (shown)  → hands lowered just enough to peek, eyes open on the field
 *
 * HOW THE EYES WORK
 *   Every frame (requestAnimationFrame) each eye measures where it is on screen, works out the angle to the
 *   current target with Math.atan2(dy, dx), and moves its pupil up to MAX_LOOK SVG units along that angle.
 *   The pupil eases toward that spot (linear interpolation), so it glides instead of snapping.
 */
(function () {
	'use strict';

	const stage = document.querySelector('[data-mascots]');
	if (!stage || !window.gsap) return;

	/* ---------------------------------------------------------------- settings */
	const reduced = !!(window.CD && CD.reduced);           // "reduce motion" in the OS → no big moves
	const MAX_LOOK = 3.6;       // how far a pupil can travel from the eye centre (SVG units; the eye is ~9 tall)
	const REACH_PX = 220;       // targets this far away (screen px) or more get the full MAX_LOOK
	const FACE_SHIFT = 0.45;    // the whole face drifts this fraction of the pupil move → feels 3-D
	const EASE = 0.16;          // 0..1 — share of the remaining distance covered each frame
	const HEART_COLORS = ['#F2A7B5', '#FFCB6B', '#C46CF2', '#E86A6A'];

	/* ---------------------------------------------------------------- collect the mascots */
	const mascots = [...stage.querySelectorAll('.mascot')].map((el, i) => {
		const q = (s) => el.querySelector(s);
		return {
			el,
			i,
			name: el.dataset.mascot,
			shadow: q('.m-shadow'),
			float: q('.m-float'),   // idle bob
			react: q('.m-react'),   // hover / click reactions
			lean: q('.m-lean'),     // leaning toward / away from the form
			face: q('.m-face'),
			brows: q('.m-brows'),
			cheeks: q('.m-cheeks'),
			smile: q('.mouth-smile'),
			mouthO: q('.mouth-o'),
			hands: [...el.querySelectorAll('.hand')].map((h) => ({
				el: h,
				left: h.classList.contains('hand-l'),
				restX: +h.dataset.cx,
				restY: +h.dataset.cy,
				eyeX: +h.dataset.eyeX,
				eyeY: +h.dataset.eyeY,
			})),
			eyes: [...el.querySelectorAll('.eye')].map((eye) => ({
				white: eye.querySelector('.eye-white'), // measured each frame for the eye's screen position
				pupil: eye.querySelector('.pupil'),     // moved each frame
				open: eye.querySelector('.eye-open'),   // squashed to "close" the eye
				closed: eye.querySelector('.eye-closed'),
				x: 0, y: 0, s: 1,                        // current pupil offset + scale (eased)
			})),
			faceX: 0,
			faceY: 0,
			busy: false, // true while a click reaction plays, so hovers don't interrupt it
		};
	});

	/* ---------------------------------------------------------------- state */
	let mode = 'follow';
	let field = null;                      // the focused form field (curious / privacy / peek)
	const pointer = { x: innerWidth * 0.7, y: innerHeight * 0.45 };
	let lastPointerMove = 0;
	const finePointer = matchMedia('(hover: hover) and (pointer: fine)').matches;

	window.addEventListener('pointermove', (e) => {
		if (e.pointerType === 'touch') return;
		pointer.x = e.clientX;
		pointer.y = e.clientY;
		lastPointerMove = performance.now();
	}, { passive: true });

	// Touch screens (or a still mouse): every few seconds glance somewhere new so they never look frozen.
	setInterval(() => {
		if (mode !== 'follow' || performance.now() - lastPointerMove < 3000) return;
		const r = stage.getBoundingClientRect();
		pointer.x = r.left + Math.random() * r.width * 1.6 - r.width * 0.3;
		pointer.y = r.top + Math.random() * r.height * 1.4 - r.height * 0.5;
	}, 2600);

	/* ---------------------------------------------------------------- where is the text caret? */
	// Curious mascots stare at the exact spot you're typing. We measure the text before the caret
	// with an offscreen canvas using the field's own font.
	const measureCtx = document.createElement('canvas').getContext('2d');
	function caretPoint(input) {
		const r = input.getBoundingClientRect();
		const cs = getComputedStyle(input);
		measureCtx.font = `${cs.fontWeight} ${cs.fontSize} ${cs.fontFamily}`;
		let pos = input.value.length;
		try {
			if (typeof input.selectionStart === 'number') pos = input.selectionStart; // null for type=email
		} catch (e) { /* some input types don't expose the caret */ }
		const text = input.type === 'password' ? '•'.repeat(pos) : input.value.slice(0, pos);
		const pad = parseFloat(cs.paddingLeft) || 0;
		const x = r.left + pad + Math.min(measureCtx.measureText(text).width - input.scrollLeft, r.width - pad * 2);
		return { x, y: r.top + r.height / 2 };
	}

	function centerOf(el) {
		const r = el.getBoundingClientRect();
		return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
	}

	/* ---------------------------------------------------------------- the frame loop (eyes) */
	let lastActive = null;
	function frame() {
		// 0. Safety net: notice focus changes even when no focus event fired (autofill, password managers, scripts).
		if (document.activeElement !== lastActive) {
			lastActive = document.activeElement;
			evaluate();
		}

		// 1. Decide what everyone is looking at this frame.
		let target = pointer;
		if ((mode === 'curious' || mode === 'peek') && field) target = caretPoint(field);

		for (const m of mascots) {
			let faceTX = 0;
			let faceTY = 0;

			m.eyes.forEach((eye, k) => {
				const c = centerOf(eye.white);
				let tx;
				let ty;

				if (mode === 'privacy' && field) {
					// 2a. Look the OTHER way: mirror the field's direction through the eye.
					const f = centerOf(field);
					const angle = Math.atan2(c.y - f.y, c.x - f.x); // pointing away from the field
					tx = Math.cos(angle) * MAX_LOOK;
					ty = Math.sin(angle) * MAX_LOOK;
				} else {
					// 2b. Look AT the target. atan2 gives the direction; distance decides how far the pupil goes.
					const dx = target.x - c.x;
					const dy = target.y - c.y;
					const angle = Math.atan2(dy, dx);
					const reach = mode === 'follow' ? Math.min(1, Math.hypot(dx, dy) / REACH_PX) : 1; // staring = full reach
					tx = Math.cos(angle) * MAX_LOOK * reach;
					ty = Math.sin(angle) * MAX_LOOK * reach;
				}

				// 3. Ease toward the goal (lerp) so pupils glide.
				eye.x += (tx - eye.x) * EASE;
				eye.y += (ty - eye.y) * EASE;
				eye.s += ((mode === 'curious' ? 1.18 : 1) - eye.s) * EASE; // curious → bigger pupils
				eye.pupil.setAttribute('transform', `translate(${eye.x.toFixed(2)} ${eye.y.toFixed(2)}) scale(${eye.s.toFixed(3)})`);

				if (k === 0) {
					faceTX = tx * FACE_SHIFT;
					faceTY = ty * FACE_SHIFT;
				}
			});

			// 4. Nudge the whole face a little the same way → a subtle head turn.
			m.faceX += (faceTX - m.faceX) * EASE;
			m.faceY += (faceTY - m.faceY) * EASE;
			m.face.setAttribute('transform', `translate(${m.faceX.toFixed(2)} ${m.faceY.toFixed(2)})`);
		}
		requestAnimationFrame(frame);
	}

	/* ---------------------------------------------------------------- eyes open / closed */
	function setEyes(m, closed, instant) {
		m.eyes.forEach((eye) => {
			gsap.killTweensOf(eye.open); // stop a blink that might be mid-way
			gsap.to(eye.open, { scaleY: closed ? 0.08 : 1, transformOrigin: '50% 50%', duration: instant ? 0 : 0.18, ease: 'power2.out' });
			gsap.to(eye.closed, { opacity: closed ? 1 : 0, duration: instant ? 0 : 0.15 });
		});
	}

	// Random blinks, each mascot on its own clock, only while eyes are meant to be open.
	function scheduleBlink(m) {
		setTimeout(() => {
			if ((mode === 'follow' || mode === 'curious') && !m.busy) {
				m.eyes.forEach((eye) =>
					gsap.fromTo(eye.open, { scaleY: 1 }, { scaleY: 0.1, transformOrigin: '50% 50%', duration: 0.07, yoyo: true, repeat: 1, ease: 'power1.in' })
				);
			}
			scheduleBlink(m);
		}, 2200 + Math.random() * 4200);
	}

	/* ---------------------------------------------------------------- modes */
	function setMode(next) {
		if (next === mode && next !== 'curious') return;
		mode = next;
		const fc = field ? centerOf(field) : null;
		const t = reduced ? 0 : 1; // reduced motion → instant changes

		mascots.forEach((m) => {
			const d = t * m.i * 0.05; // a tiny stagger so they react like a little crowd
			const mc = centerOf(m.el);
			const towardForm = fc ? Math.max(-1, Math.min(1, (fc.x - mc.x) / 160)) : 0; // −1 … 1

			// Hands: cover the eyes (privacy), half-cover (peek) or rest at the sides.
			m.hands.forEach((h) => {
				let x = 0;
				let y = 0;
				if (mode === 'privacy') {
					x = h.eyeX - h.restX;
					y = h.eyeY - h.restY;
				} else if (mode === 'peek') {
					x = h.eyeX - h.restX + (h.left ? -7 : 7);
					y = h.eyeY - h.restY + 11;
				}
				gsap.to(h.el, {
					x, y,
					// palms grow a bit when they cover the eyes, so they read as hands, not glasses
					scale: mode === 'privacy' ? 1.45 : mode === 'peek' ? 1.25 : 1,
					rotation: mode === 'privacy' ? (h.left ? -18 : 18) : 0,
					transformOrigin: '50% 50%',
					duration: 0.4 * t,
					delay: d,
					ease: mode === 'privacy' ? 'back.out(1.7)' : 'power3.out',
				});
			});

			// Eyes: shut for privacy, open otherwise.
			setEyes(m, mode === 'privacy', !t);

			// Lean: toward the form when curious, away when being polite, upright otherwise.
			const lean = mode === 'curious' ? 6 * towardForm : mode === 'privacy' ? -7 * (towardForm || 1) : mode === 'peek' ? 3 * towardForm : 0;
			gsap.to(m.lean, { rotation: lean, transformOrigin: '50% 100%', duration: 0.5 * t, delay: d, ease: 'power3.out' });

			// Face details.
			gsap.to(m.brows, { opacity: mode === 'curious' ? 1 : 0, y: mode === 'curious' ? -1.5 : 0, duration: 0.25 * t });
			gsap.to(m.mouthO, { opacity: mode === 'curious' ? 1 : 0, duration: 0.2 * t });
			gsap.to(m.smile, { opacity: mode === 'curious' ? 0 : 1, duration: 0.2 * t });
			gsap.to(m.cheeks, { opacity: mode === 'privacy' || mode === 'peek' ? 0.95 : 0.55, duration: 0.3 * t }); // shy blush
		});
	}

	// Work out the mode from the focused element.
	const FIELD = '.auth-card input:not([type=hidden]):not([type=checkbox]):not([type=radio])';
	document.querySelectorAll('.auth-card input[type=password]').forEach((i) => (i.dataset.secret = '1')); // remember even after "show password" turns them into text
	function evaluate() {
		let el = document.activeElement;
		// The show/hide-password eye button belongs to its password field.
		if (el && el.matches('[data-toggle-password]')) el = el.parentElement.querySelector('input');
		if (el && el.matches(FIELD)) {
			field = el;
			if (el.dataset.secret) setMode(el.type === 'password' ? 'privacy' : 'peek');
			else setMode('curious');
		} else {
			field = null;
			setMode('follow');
		}
	}
	document.addEventListener('focusin', evaluate);
	document.addEventListener('focusout', () => setTimeout(evaluate, 0)); // wait until focus has landed somewhere new
	document.addEventListener('click', (e) => {
		if (e.target.closest('[data-toggle-password]')) setTimeout(evaluate, 0); // type just switched password ⇄ text
	});

	/* ---------------------------------------------------------------- hover + click reactions */
	const HOVER = {
		coffee: (m) => gsap.timeline()                                                    // a little hop + steam puff
			.to(m.react, { y: -9, duration: 0.18, ease: 'power2.out' })
			.to(m.react, { y: 0, duration: 0.45, ease: 'bounce.out' })
			.fromTo(m.el.querySelectorAll('.m-steam path'), { y: 0, opacity: 1 }, { y: -6, opacity: 0.4, duration: 0.5, stagger: 0.08, yoyo: true, repeat: 1 }, 0),
		cake: (m) => gsap.to(m.react, { keyframes: { rotation: [-7, 6, -3, 0] }, transformOrigin: '50% 100%', duration: 0.6, ease: 'power1.inOut' }), // wobble like jelly
		croissant: (m) => gsap.timeline()                                                 // squash and stretch
			.to(m.react, { scaleY: 0.86, scaleX: 1.1, transformOrigin: '50% 100%', duration: 0.15, ease: 'power2.out' })
			.to(m.react, { scaleY: 1, scaleX: 1, duration: 0.6, ease: 'elastic.out(1, .35)' }),
		boba: (m) => gsap.timeline()                                                      // straw wiggle + pearls jiggle
			.to(m.el.querySelector('.m-straw'), { keyframes: { rotation: [8, -6, 4, 0] }, transformOrigin: '50% 100%', duration: 0.6 }, 0)
			.to(m.el.querySelectorAll('.m-pearls circle'), { y: -5, duration: 0.15, stagger: 0.04, yoyo: true, repeat: 1, ease: 'power2.out' }, 0),
	};

	const CLICK = {
		coffee: (m) => gsap.timeline()                                                    // full spin
			.to(m.react, { rotation: '+=360', transformOrigin: '50% 60%', duration: 0.9, ease: 'back.inOut(1.4)' })
			.set(m.react, { rotation: 0 }),
		cake: (m) => gsap.timeline()                                                      // big jump, strawberry pops off and lands
			.to(m.react, { scaleY: 0.85, scaleX: 1.08, transformOrigin: '50% 100%', duration: 0.12 })
			.to(m.react, { y: -34, scaleY: 1.06, scaleX: 0.96, duration: 0.3, ease: 'power2.out' })
			.to(m.el.querySelector('.m-berry'), { y: -16, rotation: 25, transformOrigin: '50% 50%', duration: 0.3, ease: 'power2.out' }, '<')
			.to(m.react, { y: 0, scaleY: 1, scaleX: 1, duration: 0.55, ease: 'bounce.out' })
			.to(m.el.querySelector('.m-berry'), { y: 0, rotation: 0, duration: 0.55, ease: 'bounce.out' }, '<'),
		croissant: (m) => gsap.timeline()                                                 // hop + flip
			.to(m.react, { y: -22, duration: 0.25, ease: 'power2.out' })
			.to(m.react, { scaleX: -1, transformOrigin: '50% 50%', duration: 0.25, ease: 'power1.in' }, '<')
			.to(m.react, { scaleX: 1, duration: 0.25, ease: 'power1.out' })
			.to(m.react, { y: 0, duration: 0.45, ease: 'bounce.out' }, '<'),
		boba: (m) => gsap.timeline()                                                      // jump, pearls fly up and fall back
			.to(m.react, { y: -26, duration: 0.25, ease: 'power2.out' })
			.to(m.el.querySelectorAll('.m-pearls circle'), { y: -22, duration: 0.28, stagger: 0.03, ease: 'power2.out' }, '<')
			.to(m.react, { y: 0, duration: 0.5, ease: 'bounce.out' })
			.to(m.el.querySelectorAll('.m-pearls circle'), { y: 0, duration: 0.5, stagger: 0.03, ease: 'bounce.out' }, '<'),
	};

	// Hearts float up from the mascot's head.
	function hearts(m, count = 5) {
		const r = m.el.getBoundingClientRect();
		for (let k = 0; k < count; k++) {
			const h = document.createElement('span');
			h.className = 'mascot-heart';
			h.innerHTML = `<svg viewBox="0 0 24 24"><path fill="${HEART_COLORS[k % HEART_COLORS.length]}" d="M12 21s-7.5-4.6-10-9.3C.4 8.4 2.3 4.5 6 4.5c2.2 0 3.6 1.3 4.2 2.5h.1c.6-1.2 2-2.5 4.2-2.5 3.7 0 5.6 3.9 4 7.2C19.5 16.4 12 21 12 21Z"/></svg>`;
			h.style.left = r.left + r.width / 2 - 11 + 'px';
			h.style.top = r.top + r.height * 0.15 + 'px';
			document.body.appendChild(h);
			gsap.fromTo(h,
				{ scale: 0.3, opacity: 1, x: 0, y: 0, rotation: 0 },
				{
					scale: 0.7 + Math.random() * 0.6,
					x: reduced ? 0 : (Math.random() - 0.5) * 110,
					y: reduced ? -20 : -70 - Math.random() * 70,
					rotation: (Math.random() - 0.5) * 50,
					opacity: 0,
					duration: 1.1 + Math.random() * 0.5,
					ease: 'power2.out',
					delay: k * 0.05,
					onComplete: () => h.remove(),
				});
		}
	}

	mascots.forEach((m) => {
		m.el.addEventListener('pointerenter', (e) => {
			if (e.pointerType !== 'mouse' || m.busy || reduced) return;
			HOVER[m.name](m);
		});
		m.el.addEventListener('click', () => {
			hearts(m);
			if (m.busy || reduced) return;
			m.busy = true;
			gsap.killTweensOf(m.react);
			CLICK[m.name](m).eventCallback('onComplete', () => (m.busy = false));
		});
	});

	/* ---------------------------------------------------------------- idle life + start */
	mascots.forEach((m) => {
		if (!reduced) {
			const dur = 1.5 + m.i * 0.22;
			gsap.to(m.float, { y: -4, duration: dur, repeat: -1, yoyo: true, ease: 'sine.inOut', delay: m.i * 0.3 });
			gsap.to(m.shadow, { scaleX: 0.88, opacity: 0.7, transformOrigin: '50% 50%', duration: dur, repeat: -1, yoyo: true, ease: 'sine.inOut', delay: m.i * 0.3 });
			// entrance: drop in one after another
			gsap.from(m.react, { y: -60, opacity: 0, duration: 0.8, delay: 0.15 + m.i * 0.1, ease: 'bounce.out' });
		}
		scheduleBlink(m);
	});
	if (!finePointer) lastPointerMove = 0; // touch: start wandering straight away
	evaluate();                            // a field may already be focused (autofocus)
	requestAnimationFrame(frame);
})();
