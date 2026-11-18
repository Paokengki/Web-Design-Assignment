/* Public café registration form. */
(function () {
	'use strict';
	const API = 'api/customer/register_cafe.php';
	const form = document.getElementById('cafe-register-form');
	const sent = document.getElementById('cafe-register-sent');

	CD.ajaxForm(form, API, {
		onSuccess: () => {
			form.hidden = true;
			sent.hidden = false;
		},
	});

	document.getElementById('cafe-register-again').addEventListener('click', () => {
		CD.clearErrors(form);
		form.reset();
		sent.hidden = true;
		form.hidden = false;
	});
})();
