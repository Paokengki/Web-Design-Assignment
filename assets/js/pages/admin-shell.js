/* Loaded on every control-panel page: "My account" password change for the signed-in staff member. */
(function () {
	'use strict';
	const form = document.getElementById('account-form');
	if (!form) return;
	CD.ajaxForm(form, 'api/admin/account.php', {
		onSuccess: () => {
			form.reset();
			form.querySelector('[data-strength]').dispatchEvent(new Event('input'));
			CD.modal.close('account-modal');
			CD.toast('Password updated');
		},
	});
})();
