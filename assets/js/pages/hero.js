/*
 * Scroll-driven coffee-cup image sequence (Apple-style).
 *
 * - <section class="hero" data-frames-path data-frame-count data-frame-ext> is ~5 screens tall;
 *   its child .hero-sticky stays pinned while you scroll through it.
 * - A GSAP timeline scrubbed by ScrollTrigger drives: frame index, text beats, dark fade, progress bar.
 * - Frames load progressively (coarse first, then fill gaps) so the cup animates before 100% is loaded;
 *   the closest already-loaded frame is drawn meanwhile.
 * - Phones load every 2nd frame. Reduced-motion users get a static frame.
 */
(function () {
	'use strict';
	const hero = document.querySelector('.hero');
	if (!hero) return;

	const canvas = hero.querySelector('.hero-canvas');
	const ctx = canvas.getContext('2d', { alpha: false });
	const nav = document.querySelector('[data-nav]');
	const loaderText = hero.querySelector('.hero-loader b');
	const BG = '#fdfdfd';

	const path = hero.dataset.framesPath;
	const ext = hero.dataset.frameExt || '.webp';
	const total = parseInt(hero.dataset.frameCount, 10) || 300;
	const stride = window.matchMedia('(max-width: 767px)').matches ? 2 : 1;
	const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	const hasGsap = typeof gsap !== 'undefined' && typeof ScrollTrigger !== 'undefined';

	const frames = new Array(total).fill(null); // index 0 → frame_0001
	const src = (i) => `${path}${String(i + 1).padStart(4, '0')}${ext}`;

	/* ---------------------------------------------------------- drawing */
	let current = 0;      // frame we want to show
	let drawn = '';       // frame + position actually on the canvas
	// Horizontal position of the cup (0..1 of the viewport). It slides away from the side the text is on.
	const stacked = () => window.innerWidth < 900; // text sits under the cup on small screens
	const view = { focus: stacked() ? 0.5 : 0.62 };
	const CUP_X = 0.47; // cup centre, as a fraction of the source frame width
	let w = 0, h = 0, dpr = 1;

	function resize() {
		dpr = Math.min(window.devicePixelRatio || 1, 2);
		w = canvas.clientWidth;
		h = canvas.clientHeight;
		canvas.width = Math.round(w * dpr);
		canvas.height = Math.round(h * dpr);
		drawn = '';
		render();
	}

	function nearestLoaded(i) {
		if (frames[i]) return i;
		for (let d = 1; d < total; d++) {
			if (frames[i - d]) return i - d;
			if (frames[i + d]) return i + d;
		}
		return -1;
	}

	function render() {
		const idx = nearestLoaded(current);
		const focus = stacked() ? 0.5 : view.focus;
		const key = idx + ':' + focus.toFixed(4) + ':' + canvas.width;
		if (idx < 0 || key === drawn) return;
		const img = frames[idx];
		const cw = canvas.width, ch = canvas.height;
		const iw = img.naturalWidth, ih = img.naturalHeight;
		let scale, x, y;
		if (cw / ch >= 1) {
			// landscape: fit the height (the white studio background blends into the page),
			// shrink a little on narrow windows so the cup leaves room for the text
			scale = Math.min(ch / ih, cw / (iw * 0.74));
			x = cw * focus - iw * CUP_X * scale;
			y = (ch - ih * scale) / 2;
		} else {
			// portrait: zoom on the cup (it sits ~47% across the frame) and lift it above the text card
			scale = (cw / iw) * 1.9;
			x = cw / 2 - iw * CUP_X * scale;
			y = ch * 0.36 - (ih * scale) / 2;
		}
		ctx.fillStyle = BG;
		ctx.fillRect(0, 0, cw, ch);
		ctx.drawImage(img, x, y, iw * scale, ih * scale);
		drawn = key;
	}

	let rafPending = false;
	function requestRender() {
		if (rafPending) return;
		rafPending = true;
		requestAnimationFrame(() => {
			rafPending = false;
			render();
		});
	}

	/* ---------------------------------------------------------- loading */
	function loadOrder() {
		// 0, then every 32nd, 16th, 8th ... so the whole range is coarsely covered early
		const order = [];
		const seen = new Set();
		for (let step = 32; step >= stride; step = Math.floor(step / 2)) {
			for (let i = 0; i < total; i += step) {
				if (i % stride === 0 && !seen.has(i)) {
					seen.add(i);
					order.push(i);
				}
			}
			if (step === 1) break;
		}
		return order;
	}

	function load(i) {
		return new Promise((resolve) => {
			const img = new Image();
			img.decoding = 'async';
			img.onload = () => {
				frames[i] = img;
				resolve(true);
			};
			img.onerror = () => resolve(false);
			img.src = src(i);
		});
	}

	async function preload() {
		const queue = loadOrder();
		const count = queue.length;
		let done = 0;
		const report = () => {
			const pct = Math.round((done / count) * 100);
			if (loaderText) loaderText.textContent = `Brewing ${pct}%`;
			if (done === count) hero.classList.add('is-loaded');
		};

		await load(queue.shift()); // first frame before anything else
		done++;
		hero.classList.add('is-ready');
		resize();
		report();

		const worker = async () => {
			while (queue.length) {
				await load(queue.shift());
				done++;
				report();
				requestRender();
			}
		};
		await Promise.all(Array.from({ length: 6 }, worker));
	}

	/* ---------------------------------------------------------- static fallback */
	if (reduced || !hasGsap) {
		hero.classList.add('is-static');
		current = Math.round(total * 0.4);
		load(current).then(() => {
			hero.classList.add('is-ready', 'is-loaded');
			resize();
		});
		window.addEventListener('resize', resize);
		nav && nav.classList.add('is-light');
		return;
	}

	window.addEventListener('resize', resize);
	preload();

	/* ---------------------------------------------------------- scroll timeline */
	gsap.registerPlugin(ScrollTrigger);
	const beat = (n) => hero.querySelector(`[data-beat="${n}"]`);
	const state = { frame: 0 };
	const lastFrame = total - 1;

	const tl = gsap.timeline({
		defaults: { ease: 'none' },
		scrollTrigger: {
			trigger: hero,
			start: 'top top',
			end: 'bottom bottom',
			scrub: 0.5,
			onUpdate: (self) => {
				nav && nav.classList.toggle('is-light', self.progress < 0.84);
			},
		},
	});

	// The timeline is 1 unit long, so positions below read as "percent of the hero scroll".
	tl.to(state, {
		frame: lastFrame,
		duration: 0.78,
		onUpdate: () => {
			const f = Math.round(state.frame);
			current = stride > 1 ? Math.min(lastFrame, Math.round(f / stride) * stride) : f;
			requestRender();
		},
	}, 0);

	// cup slides away from the text: text left → cup right, and so on
	const slide = (to, at) => tl.to(view, { focus: to, duration: 0.1, ease: 'power2.inOut', onUpdate: requestRender }, at);
	slide(0.38, 0.15);
	slide(0.62, 0.43);
	slide(0.5, 0.7);

	// beat 1: visible on arrival, leaves upward
	tl.to(beat(1), { opacity: 0, y: -60, duration: 0.07 }, 0.12);
	// beat 2 + 3: rise in, hold, rise out
	[[2, 0.22, 0.42], [3, 0.5, 0.7]].forEach(([n, inAt, outAt]) => {
		tl.fromTo(beat(n), { opacity: 0, y: 70 }, { opacity: 1, y: 0, duration: 0.07 }, inAt);
		tl.to(beat(n), { opacity: 0, y: -60, duration: 0.07 }, outAt);
	});
	// the cup has faded to white by now → fade the stage to the dark app background
	tl.to(hero.querySelector('.hero-dim'), { opacity: 1, duration: 0.12 }, 0.76);
	tl.to(hero.querySelector('.hero-hud'), { opacity: 0, duration: 0.05 }, 0.74);
	tl.fromTo(beat(4), { opacity: 0, scale: 0.92, y: 40 }, { opacity: 1, scale: 1, y: 0, duration: 0.1 }, 0.86);
	tl.set({}, {}, 1); // pad to exactly 1

	gsap.to(hero.querySelector('.hero-progress i'), {
		scaleX: 1,
		ease: 'none',
		scrollTrigger: { trigger: hero, start: 'top top', end: 'bottom bottom', scrub: true },
	});

	gsap.set([beat(2), beat(3), beat(4)], { opacity: 0 });
	// intro on load
	gsap.from(beat(1).children, { y: 30, opacity: 0, duration: 1.1, ease: 'expo.out', stagger: 0.1, delay: 0.2 });
	nav && nav.classList.add('is-light');
})();
