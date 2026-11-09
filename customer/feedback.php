<?php
/** Feedback — replaces sidebar/contact.php (formerly customer/contact.php). Messages land in the admin Feedback inbox. */
require __DIR__ . '/../app/layout/master.php';

$user = current_user();
layout_start(['title' => 'Feedback', 'nav' => 'shop', 'active' => 'feedback', 'styles' => ['pages/shop.css']]);
?>
<div class="contact-grid">
	<section data-reveal>
		<span class="eyebrow">Feedback</span>
		<h1 class="contact-title">Tell us how we <span class="grad-text">did.</span></h1>
		<p class="muted">A great cup, a late order or a café you think we should add — we read every message and reply within one working day.</p>
		<?php /* contact details carried over from the old contact page */ ?>
		<ul class="contact-facts">
			<li><?= icon('phone') ?><a href="tel:01234567889">012-34567889</a></li>
			<li><?= icon('mail') ?><a href="mailto:IDk@gmail.com">IDk@gmail.com</a></li>
			<li><?= icon('pin') ?>TARUMT Arena</li>
		</ul>
		<dl class="hours">
			<div><dt>Mon – Fri</dt><dd>9:00am – 8:00pm</dd></div>
			<div><dt>Saturday</dt><dd>9:00am – 6:00pm</dd></div>
			<div><dt>Sunday</dt><dd>9:00am – 5:00pm</dd></div>
		</dl>
	</section>

	<section class="glass card contact-card" data-reveal>
		<form id="feedback-form" novalidate>
			<input type="hidden" name="action" value="send">
			<label class="hp" aria-hidden="true">Website <input name="website" tabindex="-1" autocomplete="off"></label>
			<div class="form-grid">
				<label class="field"><span>Name</span><input class="input" name="name" required autocomplete="name" value="<?= e($user['User_Name'] ?? '') ?>"><span class="field-error"></span></label>
				<label class="field"><span>Phone</span><input class="input" type="tel" name="phone" required autocomplete="tel" value="<?= e($user['Contain_number'] ?? '') ?>"><span class="field-error"></span></label>
				<label class="field span-2"><span>Email</span><input class="input" type="email" name="email" required autocomplete="email" value="<?= e($user['Email'] ?? '') ?>"><span class="field-error"></span></label>
				<label class="field span-2"><span>Your feedback <em class="tiny" id="msg-count">0 / 2000</em></span><textarea class="textarea" name="message" rows="6" maxlength="2000" required></textarea><span class="field-error"></span></label>
			</div>
			<div class="form-actions"><button class="btn btn-primary btn-lg" type="submit" data-magnetic><?= icon('send') ?>Send feedback</button></div>
		</form>
		<div class="sent" id="feedback-sent" hidden>
			<div class="success-mark"><?= icon('check') ?></div>
			<h2>Feedback sent.</h2>
			<p class="muted">Thank you — we’ll get back to you soon.</p>
			<button class="btn btn-glass" type="button" id="feedback-again">Send more feedback</button>
		</div>
	</section>
</div>
<?php layout_end(['pages/feedback.js']);
