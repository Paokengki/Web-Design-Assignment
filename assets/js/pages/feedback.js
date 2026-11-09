/* Feedback form: AJAX send, character counter, success state. */
(function () {
	'use strict';
	const form = document.getElementById('feedback-form');
	const sent = document.getElementById('feedback-sent');
	const counter = document.getElementById('msg-count');
	form.elements.message.addEventListener('input', (e) => (counter.textContent = `${e.target.value.length} / 2000`));

	CD.ajaxForm(form, 'api/shop/feedback.php', {
		onSuccess: () => {
			form.hidden = true;
			sent.hidden = false;
			if (window.gsap && !CD.reduced) gsap.from(sent.children, { y: 20, opacity: 0, stagger: 0.08, duration: 0.6, ease: 'expo.out' });
		},
	});
	document.getElementById('feedback-again').addEventListener('click', () => {
		form.elements.message.value = '';
		counter.textContent = '0 / 2000';
		sent.hidden = true;
		form.hidden = false;
	});
})();
