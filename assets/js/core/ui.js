/* CafeDash core/ui.js — icons, toasts, forms, modals, confirm dialog, action menus, password meter. */
(function () {
	'use strict';
	const CD = window.CD;
	const reduced = CD.reduced;
	const hasGsap = typeof window.gsap !== 'undefined';

	/* ------------------------------------------------ icons */
	const ICONS = {
		edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
		trash: '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>',
		pause: '<rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/>',
		play: '<path d="m7 4 13 8-13 8Z"/>',
		dots: '<circle cx="5" cy="12" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="19" cy="12" r="1.3"/>',
		key: '<circle cx="7.5" cy="15.5" r="4.5"/><path d="m10.7 12.3 9.3-9.3M17 6l3 3M14 9l2 2"/>',
		ban: '<circle cx="12" cy="12" r="9"/><path d="m5.6 5.6 12.8 12.8"/>',
		check: '<path d="M20 6 9 17l-5-5"/>',
		shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>',
		star: '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9Z"/>',
		copy: '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>',
		x: '<path d="M18 6 6 18M6 6l12 12"/>',
		user: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		power: '<path d="M12 2v10"/><path d="M18.4 6.6a9 9 0 1 1-12.8 0"/>',
	};
	Object.assign(ICONS, window.CD_ICONS || {}); // the PHP icon set (app/layout/icons.php) is the source of truth
	CD.icon = (name) => `<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">${ICONS[name] || ''}</svg>`;

	/* ------------------------------------------------ toasts */
	CD.toast = (message, type = 'success') => {
		const stack = document.getElementById('toasts');
		if (!stack) return;
		const el = document.createElement('div');
		el.className = `toast ${type}`;
		el.textContent = message;
		stack.appendChild(el);
		if (hasGsap) gsap.from(el, { y: 20, opacity: 0, duration: 0.4, ease: 'power3.out' });
		setTimeout(() => {
			if (hasGsap) gsap.to(el, { x: 40, opacity: 0, duration: 0.3, onComplete: () => el.remove() });
			else el.remove();
		}, 3800);
	};

	/* ------------------------------------------------ busy buttons */
	CD.busy = (btn, on) => {
		if (!btn) return;
		btn.classList.toggle('is-loading', on);
		btn.disabled = on;
	};

	/* ------------------------------------------------ forms */
	CD.fillForm = (form, data = {}) => {
		[...form.elements].forEach((el) => {
			if (!el.name || el.type === 'file' || el.name === '_csrf' || el.name === 'action') return;
			const v = data[el.name];
			if (el.type === 'checkbox') el.checked = !!Number(v) || v === true;
			else if (el.type !== 'radio') el.value = v ?? '';
		});
	};

	CD.clearErrors = (form) => {
		form.querySelectorAll('[aria-invalid]').forEach((el) => el.removeAttribute('aria-invalid'));
		form.querySelectorAll('.field-error').forEach((el) => (el.textContent = ''));
	};

	CD.showErrors = (form, fields = {}) => {
		Object.entries(fields).forEach(([name, msg]) => {
			const el = form.elements[name];
			if (!el) return;
			el.setAttribute('aria-invalid', 'true');
			const holder = el.closest('.field')?.querySelector('.field-error');
			if (holder) holder.textContent = msg;
		});
		const first = form.querySelector('[aria-invalid="true"]');
		if (first) first.focus();
	};

	/**
	 * Wire a form to an endpoint. opts.onSuccess(data, form) runs after ok.
	 * Field-level errors from PHP (data.fields) are shown beside each input.
	 */
	CD.ajaxForm = (form, endpoint, opts = {}) => {
		form.addEventListener('submit', async (ev) => {
			ev.preventDefault();
			CD.clearErrors(form);
			const btn = form.querySelector('[type="submit"]');
			CD.busy(btn, true);
			try {
				const data = await CD.api(endpoint, new FormData(form));
				opts.onSuccess && opts.onSuccess(data, form);
			} catch (err) {
				if (err.fields) CD.showErrors(form, err.fields);
				CD.toast(err.message, 'error');
			} finally {
				CD.busy(btn, false);
			}
		});
	};

	/* ------------------------------------------------ modals
	 * While a modal is open, everything else on the page (header, cart drawer, content) is made `inert`:
	 * it can't be clicked, focused or tabbed to. Only the top-most modal and the toasts stay live.
	 * Modals marked data-static (confirm dialogs) also ignore backdrop clicks: the user must answer. */
	const openModals = () => [...document.querySelectorAll('.modal.is-open')];
	function syncInert() {
		const top = openModals().pop();
		[...document.body.children].forEach((el) => {
			if (el.tagName === 'SCRIPT' || el.id === 'toasts') return;
			el.inert = !!top && el !== top;
		});
	}
	function unlockScrollIfIdle() {
		if (!document.querySelector('.modal.is-open, .drawer.is-open')) document.body.style.overflow = '';
	}
	CD.modal = {
		open(id) {
			const m = typeof id === 'string' ? document.getElementById(id) : id;
			if (!m) return;
			// modals must be direct children of <body> so they are not made inert along with the page
			if (m.parentElement !== document.body) document.body.appendChild(m);
			m._returnFocus = document.activeElement;
			m.classList.add('is-open');
			document.body.style.overflow = 'hidden';
			syncInert();
			const panel = m.querySelector('.modal-panel');
			if (hasGsap && !reduced) {
				gsap.fromTo(m.querySelector('.modal-backdrop'), { opacity: 0 }, { opacity: 1, duration: 0.25 });
				gsap.fromTo(panel, { y: 24, scale: 0.97, opacity: 0 }, { y: 0, scale: 1, opacity: 1, duration: 0.45, ease: 'expo.out' });
			}
			setTimeout(() => (m.querySelector('[autofocus], input:not([type=hidden]):not([disabled]), select, textarea') || m.querySelector('[data-act="no"], button'))?.focus(), 60);
		},
		close(id) {
			const m = typeof id === 'string' ? document.getElementById(id) : id;
			if (!m || !m.classList.contains('is-open')) return;
			m.classList.remove('is-open'); // removed at once so the page underneath is live again immediately
			m.classList.add('is-closing');
			syncInert();
			const done = () => {
				m.classList.remove('is-closing');
				unlockScrollIfIdle();
				const back = m._returnFocus;
				if (back && back.isConnected && !back.closest('[inert]')) back.focus();
				m.dispatchEvent(new CustomEvent('modal:closed'));
			};
			if (hasGsap && !reduced) {
				gsap.to(m.querySelector('.modal-panel'), { y: 16, opacity: 0, scale: 0.98, duration: 0.2, ease: 'power2.in' });
				gsap.to(m.querySelector('.modal-backdrop'), { opacity: 0, duration: 0.2, onComplete: done });
			} else done();
		},
	};
	function nudge(m) {
		// a click outside a confirm dialog gives a small shake instead of closing it
		const panel = m.querySelector('.modal-panel');
		if (hasGsap && !reduced) gsap.fromTo(panel, { x: 0 }, { keyframes: { x: [-10, 10, -6, 6, 0] }, duration: 0.4, ease: 'power1.out' });
		m.querySelector('[data-act="no"]')?.focus();
	}
	document.addEventListener('click', (ev) => {
		const opener = ev.target.closest('[data-open-modal]');
		if (opener) {
			ev.preventDefault();
			CD.modal.open(opener.dataset.openModal);
		}
		const closer = ev.target.closest('[data-close-modal], .modal-backdrop');
		if (!closer) return;
		const m = closer.closest('.modal');
		if (closer.classList.contains('modal-backdrop') && m.hasAttribute('data-static')) return nudge(m);
		CD.modal.close(m);
	});
	document.addEventListener('keydown', (ev) => {
		if (ev.key !== 'Escape') return;
		if (CD.closeMenu()) return;
		const top = openModals().pop();
		if (!top) return;
		ev.stopImmediatePropagation(); // Escape closes only the top dialog, never the cart drawer behind it
		if (top.hasAttribute('data-static')) top.querySelector('[data-act="no"]')?.click(); // same as pressing Cancel
		else CD.modal.close(top);
	}, true);

	/* ------------------------------------------------ confirm dialog */
	CD.confirm = ({ title, body = '', confirmText = 'Confirm', danger = false, input = null }) =>
		new Promise((resolve) => {
			const m = document.createElement('div');
			m.className = 'modal modal-confirm';
			m.setAttribute('data-static', ''); // must click Confirm or Cancel
			m.innerHTML = `
				<div class="modal-backdrop"></div>
				<div class="modal-panel" role="alertdialog" aria-modal="true" aria-labelledby="cf-title" aria-describedby="cf-body">
					<div class="modal-head"><div><h2 id="cf-title">${CD.esc(title)}</h2>${body ? `<p id="cf-body">${body}</p>` : ''}</div></div>
					${input ? `<label class="field"><span>${CD.esc(input.label)}</span><input class="input" type="${input.type || 'text'}" autocomplete="${input.autocomplete || 'off'}"></label>` : ''}
					<div class="form-actions">
						<button type="button" class="btn btn-ghost" data-act="no">Cancel</button>
						<button type="button" class="btn ${danger ? 'btn-danger' : 'btn-primary'}" data-act="yes">${CD.esc(confirmText)}</button>
					</div>
				</div>`;
			document.body.appendChild(m);
			let answered = false;
			const finish = (val) => {
				if (answered) return;
				answered = true;
				CD.modal.close(m);
				resolve(val);
			};
			m.addEventListener('click', (ev) => {
				const act = ev.target.closest('[data-act]')?.dataset.act;
				if (act === 'yes') finish(input ? m.querySelector('input').value : true);
				if (act === 'no') finish(false);
			});
			m.querySelector('input')?.addEventListener('keydown', (ev) => ev.key === 'Enter' && finish(ev.target.value));
			m.addEventListener('modal:closed', () => {
				finish(false);
				m.remove();
			});
			CD.modal.open(m);
		});

	/* ------------------------------------------------ action menu */
	let openMenu = null;
	CD.closeMenu = () => {
		if (!openMenu) return false;
		openMenu.remove();
		openMenu = null;
		return true;
	};
	/** items: [{label, icon, danger, onClick}] | {sep:true} | {heading:'...'} */
	CD.menu = (anchor, items) => {
		CD.closeMenu();
		const el = document.createElement('div');
		el.className = 'menu';
		el.setAttribute('role', 'menu');
		items.forEach((it) => {
			if (it.sep) return el.appendChild(document.createElement('hr'));
			if (it.heading) {
				const h = document.createElement('div');
				h.className = 'menu-label';
				h.textContent = it.heading;
				return el.appendChild(h);
			}
			const b = document.createElement('button');
			b.type = 'button';
			b.setAttribute('role', 'menuitem');
			if (it.danger) b.className = 'danger';
			b.innerHTML = `${CD.icon(it.icon)}<span>${CD.esc(it.label)}</span>`;
			b.addEventListener('click', () => {
				CD.closeMenu();
				it.onClick && it.onClick();
			});
			el.appendChild(b);
		});
		document.body.appendChild(el);
		const r = anchor.getBoundingClientRect();
		const w = el.offsetWidth, h = el.offsetHeight;
		let left = Math.min(r.right - w, window.innerWidth - w - 8);
		let top = r.bottom + 6;
		if (top + h > window.innerHeight - 8) top = Math.max(8, r.top - h - 6);
		el.style.left = Math.max(8, left) + 'px';
		el.style.top = top + 'px';
		if (hasGsap && !reduced) gsap.from(el, { y: -6, opacity: 0, duration: 0.2, ease: 'power2.out' });
		el.querySelector('button')?.focus();
		openMenu = el;
	};
	document.addEventListener('click', (ev) => {
		if (openMenu && !openMenu.contains(ev.target) && !ev.target.closest('[data-menu-trigger]')) CD.closeMenu();
	});
	window.addEventListener('scroll', () => CD.closeMenu(), { passive: true });

	/* ------------------------------------------------ password strength */
	CD.scorePassword = (pw) => {
		const rules = {
			length: pw.length >= 8,
			mixed: /[a-z]/.test(pw) && /[A-Z]/.test(pw),
			number: /\d/.test(pw),
			symbol: /[^A-Za-z0-9]/.test(pw),
		};
		let score = Object.values(rules).filter(Boolean).length;
		if (pw.length >= 14) score = Math.min(4, score + 1);
		if (!rules.length) score = Math.min(score, 1);
		if (/^(.)\1+$/.test(pw) || /password|12345|qwerty/i.test(pw)) score = Math.min(score, 1);
		score = pw ? Math.max(1, score) : 0;
		return { score, rules };
	};
	const LEVELS = [
		['', 'var(--text-3)'],
		['Weak', 'var(--bad)'],
		['Fair', 'var(--warn)'],
		['Good', 'var(--info)'],
		['Strong', 'var(--ok)'],
	];
	CD.strengthMeter = (input) => {
		const target = document.querySelector(input.dataset.strength);
		if (!target) return;
		target.classList.add('strength');
		target.innerHTML = `
			<div class="strength-bar"><i></i><i></i><i></i><i></i></div>
			<div class="strength-text"><span>Password strength</span><b>—</b></div>
			<ul class="strength-rules">
				<li data-r="length">8+ characters</li><li data-r="mixed">Upper &amp; lower case</li>
				<li data-r="number">A number</li><li data-r="symbol">A symbol</li>
			</ul>`;
		const update = () => {
			const { score, rules } = CD.scorePassword(input.value);
			const [label, color] = LEVELS[score];
			target.style.setProperty('--c', color);
			target.querySelectorAll('.strength-bar i').forEach((bar, i) => bar.classList.toggle('on', i < score));
			target.querySelector('b').textContent = label || '—';
			Object.entries(rules).forEach(([k, ok]) => target.querySelector(`[data-r="${k}"]`).classList.toggle('ok', ok));
		};
		input.addEventListener('input', update);
		update();
	};

	/* ------------------------------------------------ copy */
	CD.copy = async (text) => {
		try {
			await navigator.clipboard.writeText(text);
			CD.toast('Copied to clipboard', 'info');
		} catch (e) {
			CD.toast('Copy failed — select and copy manually', 'error');
		}
	};

	// show / hide password
	document.addEventListener('click', (ev) => {
		const t = ev.target.closest('[data-toggle-password]');
		if (!t) return;
		const input = t.parentElement.querySelector('input');
		input.type = input.type === 'password' ? 'text' : 'password';
		t.setAttribute('aria-pressed', input.type === 'text');
	});

	document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('[data-strength]').forEach(CD.strengthMeter));
})();
