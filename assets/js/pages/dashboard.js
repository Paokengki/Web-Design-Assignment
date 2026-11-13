/* Module 1 — Customer dashboard: address CRUD, contact info, password, deactivate. */
(function () {
	'use strict';
	const { api, esc, icon, toast, modal } = CD;
	const ADDR_API = 'api/customer/addresses.php';
	const ACCOUNT_API = 'api/customer/account.php';

	/* ---------------------------------------------- loyalty bar */
	const loyaltyEl = document.querySelector('[data-loyalty]');
	if (loyaltyEl) {
		const { progress } = JSON.parse(loyaltyEl.dataset.loyalty);
		const bar = loyaltyEl.querySelector('.progress i');
		if (window.gsap && !CD.reduced) gsap.to(bar, { width: progress + '%', duration: 1.6, ease: 'expo.out', delay: 0.3 });
		else bar.style.width = progress + '%';
	}

	/* ---------------------------------------------- addresses */
	const grid = document.getElementById('address-grid');
	const form = document.getElementById('address-form');
	let addresses = JSON.parse(document.getElementById('address-data').textContent || '[]');

	const fullAddress = (a) =>
		[a.Line1, a.Line2, [a.Postcode, a.City].filter((v) => v && v !== '-').join(' '), a.State !== '-' ? a.State : '']
			.filter(Boolean)
			.map(esc)
			.join('<br>');

	function render(animate) {
		grid.innerHTML =
			addresses
				.map(
					(a) => `
				<article class="address ${+a.Is_default ? 'is-default' : ''}" data-id="${a.Address_ID}">
					<h4>${esc(a.Label)} ${+a.Is_default ? '<span class="pill pill-amber">Default</span>' : ''}</h4>
					<p><strong>${esc(a.Recipient)}</strong> · ${esc(a.Phone)}<br>${fullAddress(a)}</p>
					<div class="address-actions">
						<button class="icon-btn" data-act="edit" title="Edit" aria-label="Edit address">${icon('edit')}</button>
						${+a.Is_default ? '' : `<button class="icon-btn" data-act="default" title="Make default" aria-label="Make default">${icon('star')}</button>`}
						<button class="icon-btn danger" data-act="delete" title="Delete" aria-label="Delete address">${icon('trash')}</button>
					</div>
				</article>`
				)
				.join('') +
			`<button class="address-add" data-address-new type="button">
				<span><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></span>
				<span>Add address</span>
			</button>`;
		if (animate) CD.stagger(grid.children);
	}

	function openForm(a) {
		CD.clearErrors(form);
		form.reset();
		const editing = !!a;
		form.elements.action.value = editing ? 'update' : 'create';
		document.getElementById('address-title').textContent = editing ? 'Edit address' : 'Add address';
		// Clear GPS fields and status when opening the form.
		form.elements.Latitude.value = '';
		form.elements.Longitude.value = '';
		const gpsStatus = document.getElementById('gps-status');
		if (gpsStatus) gpsStatus.textContent = '';
		if (editing) {
			// migrated rows used "-" placeholders; show them as empty so the user fills them in
			const clean = Object.fromEntries(Object.entries(a).map(([k, v]) => [k, v === '-' ? '' : v]));
			CD.fillForm(form, clean);
			// If existing coordinates are present, show their status.
			if (a.Latitude && a.Longitude) {
				if (gpsStatus) gpsStatus.textContent = 'Map location saved.';
			}
		} else {
			form.elements.Address_ID.value = '';
			form.elements.State.value = 'Kuala Lumpur';
			form.elements.Is_default.checked = addresses.length === 0;
		}
		modal.open('address-modal');
	}

	document.addEventListener('click', async (ev) => {
		if (ev.target.closest('[data-address-new]')) return openForm(null);

		const btn = ev.target.closest('.address [data-act]');
		if (!btn) return;
		const card = btn.closest('.address');
		const a = addresses.find((x) => String(x.Address_ID) === card.dataset.id);
		const act = btn.dataset.act;

		if (act === 'edit') return openForm(a);

		if (act === 'default') {
			try {
				addresses = await api(ADDR_API, { action: 'set_default', Address_ID: a.Address_ID });
				render(true);
				toast(`${a.Label} is now your default address`);
			} catch (err) {
				toast(err.message, 'error');
			}
		}

		if (act === 'delete') {
			const ok = await CD.confirm({
				title: 'Delete this address?',
				body: `${esc(a.Label)} — ${esc(a.Line1)}`,
				confirmText: 'Delete',
				danger: true,
			});
			if (!ok) return;
			try {
				if (window.gsap && !CD.reduced) await gsap.to(card, { scale: 0.9, opacity: 0, duration: 0.25 });
				addresses = await api(ADDR_API, { action: 'delete', Address_ID: a.Address_ID });
				render(false);
				toast('Address deleted');
			} catch (err) {
				render(false);
				toast(err.message, 'error');
			}
		}
	});

	/* ---------------------------------------------- GPS "Use My Location" */
	const gpsBtn = document.getElementById('gps-btn');
	if (gpsBtn) {
		gpsBtn.addEventListener('click', () => {
			const gpsStatus = document.getElementById('gps-status');
			if (!navigator.geolocation) {
				if (gpsStatus) gpsStatus.textContent = 'Geolocation not supported by your browser.';
				return;
			}
			if (gpsStatus) gpsStatus.textContent = 'Requesting location...';
			gpsBtn.disabled = true;
			navigator.geolocation.getCurrentPosition(
				(pos) => {
					form.elements.Latitude.value = pos.coords.latitude.toFixed(7);
					form.elements.Longitude.value = pos.coords.longitude.toFixed(7);
					if (gpsStatus) gpsStatus.textContent = 'Location captured.';
					gpsBtn.disabled = false;
				},
				(err) => {
					const msgs = {
						1: 'Permission denied. Enable location in your browser settings.',
						2: 'Location unavailable.',
						3: 'Request timed out.',
					};
					if (gpsStatus) gpsStatus.textContent = msgs[err.code] || 'GPS error.';
					gpsBtn.disabled = false;
				},
				{ enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
			);
		});
	}

	CD.ajaxForm(form, ADDR_API, {
		onSuccess: (list) => {
			const wasEdit = form.elements.action.value === 'update';
			addresses = Array.isArray(list) ? list : (list?.addresses || []);
			modal.close('address-modal');
			render(true);
			const msg = (list && list._message) || (wasEdit ? 'Address updated' : 'Address added');
			toast(msg);
		},
	});

	render(false);

	/* ---------------------------------------------- contact info */
	CD.ajaxForm(document.getElementById('contact-form'), ACCOUNT_API, {
		onSuccess: (data) => {
			Object.entries(data).forEach(([k, v]) =>
				document.querySelectorAll(`[data-bind="${k}"]`).forEach((el) => (el.textContent = v || '—'))
			);
			modal.close('contact-modal');
			toast('Contact info saved');
		},
	});

	/* ---------------------------------------------- password */
	const pwForm = document.getElementById('password-form');
	CD.ajaxForm(pwForm, ACCOUNT_API, {
		onSuccess: () => {
			pwForm.reset();
			pwForm.querySelector('[data-strength]').dispatchEvent(new Event('input'));
			modal.close('password-modal');
			toast('Password updated');
		},
	});

	/* ---------------------------------------------- deactivate (soft delete) */
	const toggle = document.getElementById('deactivate-toggle');
	toggle.addEventListener('change', async () => {
		if (!toggle.checked) return;
		const password = await CD.confirm({
			title: 'Deactivate your account?',
			body: 'You will be signed out and won’t be able to sign in again until support restores it. Enter your password to confirm.',
			confirmText: 'Deactivate',
			danger: true,
			input: { label: 'Password', type: 'password', autocomplete: 'current-password' },
		});
		if (!password) {
			toggle.checked = false;
			return;
		}
		try {
			const data = await api(ACCOUNT_API, { action: 'deactivate', password });
			toast('Account deactivated. Goodbye for now.', 'info');
			setTimeout(() => (location.href = data.redirect), 1200);
		} catch (err) {
			toggle.checked = false;
			toast(err.message, 'error');
		}
	});
})();

/* Avatar upload: instant preview, then save to User.Profile_Image. */
(function () {
	'use strict';
	const input = document.getElementById('avatar-input');
	if (!input) return;
	const preview = document.getElementById('avatar-preview');
	input.addEventListener('change', async () => {
		const file = input.files[0];
		if (!file) return;
		if (file.size > 3 * 1024 * 1024) return CD.toast('Image must be under 3 MB.', 'error');
		const before = preview.innerHTML;
		preview.innerHTML = `<img src="${URL.createObjectURL(file)}" alt="">`;
		preview.classList.add('is-uploading');
		const fd = new FormData();
		fd.append('action', 'avatar');
		fd.append('avatar', file);
		try {
			const { url } = await CD.api('api/customer/account.php', fd);
			document.querySelectorAll('.nav-avatar .avatar').forEach((a) => (a.innerHTML = `<img src="${CD.esc(url)}" alt="">`));
			CD.toast('Profile photo updated');
		} catch (err) {
			preview.innerHTML = before;
			CD.toast(err.message, 'error');
		} finally {
			preview.classList.remove('is-uploading');
			input.value = '';
		}
	});
})();
