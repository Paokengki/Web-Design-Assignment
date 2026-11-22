<?php
/** Café registration (public). Applications land in admin/applications.php for staff approval. */
require __DIR__ . '/../app/layout/master.php';

layout_start(['title' => 'Register your café', 'nav' => 'shop', 'active' => 'register', 'styles' => ['pages/shop.css']]);
?>
<div class="contact-grid">
	<section data-reveal>
		<span class="eyebrow">For café owners</span>
		<h1 class="contact-title">Bring your café to <span class="grad-text">CafeDash.</span></h1>
		<p class="muted">Tell us about your café. Our team reviews every application, and once approved you can sign in with your email to manage orders in your kitchen view.</p>
		<ul class="contact-facts">
			<li><?= icon('check') ?>Free to apply</li>
			<li><?= icon('check') ?>Reviewed by our team</li>
			<li><?= icon('check') ?>Login details are issued after approval</li>
		</ul>
	</section>

	<section class="glass card contact-card" data-reveal>
		<form id="cafe-register-form" novalidate>
			<input type="hidden" name="action" value="submit">
			<label class="hp" aria-hidden="true">Website <input name="website" tabindex="-1" autocomplete="off"></label>
			<div class="form-grid">
				<label class="field"><span>Café name</span><input class="input" name="Name" required maxlength="120" autocomplete="organization"><span class="field-error"></span></label>
				<label class="field"><span>Type</span>
					<select class="select" name="Restaurant_type"><option>Cafe</option><option>Food</option><option>Dessert</option></select><span class="field-error"></span></label>
				<label class="field"><span>Owner / manager name</span><input class="input" name="Owner_Name" required maxlength="100" autocomplete="name"><span class="field-error"></span></label>
				<label class="field"><span>Phone</span><input class="input" type="tel" name="Contain_number" required autocomplete="tel"><span class="field-error"></span></label>
				<label class="field span-2"><span>Email <em class="tiny">(this becomes your sign-in email)</em></span><input class="input" type="email" name="Email" required autocomplete="email"><span class="field-error"></span></label>
				<label class="field span-2"><span>Address</span><textarea class="textarea" name="Address" rows="3" maxlength="255" required></textarea><span class="field-error"></span></label>
			</div>
			<div class="form-actions"><button class="btn btn-primary btn-lg" type="submit" data-magnetic><?= icon('send') ?>Submit application</button></div>
		</form>
		<div class="sent" id="cafe-register-sent" hidden>
			<div class="success-mark"><?= icon('check') ?></div>
			<h2>Application received.</h2>
			<p class="muted">Thank you — we’ll review it and contact you by email once it’s approved.</p>
			<button class="btn btn-glass" type="button" id="cafe-register-again">Submit another café</button>
		</div>
	</section>
</div>
<?php layout_end(['pages/register-cafe.js']);
