<?php
/** Checkout — replaces payment_cart/payment.php. Voucher cards + Stripe Checkout (hosted payment page). */
require __DIR__ . '/../app/layout/master.php';
require __DIR__ . '/../app/services/payments.php';

$user = current_user();
if (!$user) {
	flash('info', 'Sign in to check out. Your cart is saved.');
	redirect('auth/login.php?next=' . rawurlencode('customer/checkout.php'));
}
$sum = cart_summary((int) $user['User_ID']);
if (!$sum['items']) {
	flash('info', 'Your cart is empty — add something first.');
	redirect('customer/home.php');
}
// All saved addresses; the default one is where this order goes (order tracking reads the default too).
$addresses = q_all('SELECT Address_ID, Label, Recipient, Phone, Line1, Line2, City, Postcode, State, Is_default FROM User_Address WHERE User_ID = ? ORDER BY Is_default DESC, Created_at DESC', [(int) $user['User_ID']]);
$address = $addresses[0] ?? null;
$stripe = stripe_config();
if (isset($_GET['cancelled'])) flash('info', 'Payment cancelled — nothing was charged. Your cart is still here.');

layout_start(['title' => 'Checkout', 'nav' => 'shop', 'styles' => ['pages/shop.css']]);
?>
<a class="back-link" href="<?= e(url('customer/home.php')) ?>"><?= icon('arrow-left') ?>Keep browsing</a>
<div class="page-head" data-reveal>
	<div><span class="eyebrow">Checkout</span><h1>Almost there.</h1></div>
</div>

<div class="checkout-grid">
	<div class="dash-col">
		<section class="glass card" data-reveal>
			<div class="card-title">
				<h3>Deliver to</h3>
				<button type="button" class="btn btn-sm btn-glass" id="address-change"><?= icon('edit') ?><span><?= $address ? 'Change' : 'Add address' ?></span></button>
			</div>
			<div id="address-current" aria-live="polite"></div>
		</section>

		<section class="glass card" data-reveal>
			<div class="card-title"><h3>Payment</h3><span class="tiny"><?= icon('lock') ?> Secured by Stripe</span></div>
			<?php if (!$stripe): ?>
				<div class="flash flash-error">
					<strong>Card payments aren’t set up on this server.</strong><br>
					Run <code>composer install</code> and add <code>config/stripe.php</code> with your Stripe <em>test</em> keys
					(<code>secret_key</code>, <code>publishable_key</code>).
				</div>
			<?php else: ?>
				<div class="pay-explainer">
					<span class="pay-icon"><?= icon('lock') ?></span>
					<div>
						<strong>You’ll pay on Stripe’s secure page.</strong>
						<p class="muted">Card details never touch CafeDash. After paying you come straight back to your receipt.</p>
					</div>
				</div>
				<p class="field-error" id="pay-error" role="alert"></p>
			<?php endif; ?>
		</section>
	</div>

	<aside class="glass card summary-card" data-reveal>
		<h3>Order summary</h3>
		<div class="summary-lines" id="summary-lines"></div>

		<section class="vouchers" aria-labelledby="voucher-title">
			<div class="vouchers-head">
				<h4 id="voucher-title"><?= icon('tag') ?>Your vouchers</h4>
				<span class="tiny" id="voucher-count"></span>
			</div>
			<div class="voucher-list" id="voucher-list" role="radiogroup" aria-labelledby="voucher-title">
				<div class="skeleton" style="height:84px"></div>
			</div>
		</section>

		<dl class="totals" id="summary-totals"></dl>
		<button class="btn btn-primary btn-lg btn-block" id="pay-btn" data-stripe="<?= $stripe ? '1' : '0' ?>" <?= !$address ? 'disabled' : '' ?> data-magnetic>
			<?= icon('lock') ?><span id="pay-label">Pay</span> <span id="pay-amount"><?= money($sum['total']) ?></span>
		</button>
		<p class="tiny center" id="pay-hint" hidden></p>
		<p class="tiny center">You’ll earn about <b id="earn-points"><?= (int) floor($sum['total']) ?></b> loyalty points.</p>
	</aside>
</div>
<script type="application/json" id="cart-initial"><?= json_encode($sum, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script type="application/json" id="address-initial"><?= json_encode($addresses, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

<!-- Change delivery address: pick a saved one or add a new one, without leaving checkout -->
<div class="modal" id="address-modal">
	<div class="modal-backdrop"></div>
	<div class="modal-panel wide" role="dialog" aria-modal="true" aria-labelledby="address-modal-title">
		<div class="modal-head">
			<div><h2 id="address-modal-title">Delivery address</h2><p>Your cart stays exactly as it is.</p></div>
			<button class="icon-btn" data-close-modal aria-label="Close"><?= icon('x') ?></button>
		</div>

		<div id="address-pick">
			<div class="address-options" id="address-options" role="radiogroup" aria-label="Saved addresses"></div>
			<button type="button" class="address-add-row" id="address-new"><?= icon('plus') ?>Add a new address</button>
			<div class="form-actions">
				<button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
				<button type="button" class="btn btn-primary" id="address-use">Deliver here</button>
			</div>
		</div>

		<form id="address-form" novalidate hidden>
			<input type="hidden" name="action" value="create">
			<input type="hidden" name="Is_default" value="1">
			<div class="form-grid">
				<label class="field"><span>Label</span>
					<select class="select" name="Label"><option>Home</option><option>Work</option><option>Campus</option><option>Other</option></select>
					<span class="field-error"></span></label>
				<label class="field"><span>Recipient name</span><input class="input" name="Recipient" required autocomplete="name" value="<?= e($user['User_Name']) ?>"><span class="field-error"></span></label>
				<label class="field"><span>Phone</span><input class="input" name="Phone" type="tel" required autocomplete="tel" value="<?= e($user['Contain_number'] ?? '') ?>"><span class="field-error"></span></label>
				<label class="field"><span>Postcode</span><input class="input" name="Postcode" required inputmode="numeric" maxlength="5" autocomplete="postal-code"><span class="field-error"></span></label>
				<label class="field span-2"><span>Address line 1</span><input class="input" name="Line1" required autocomplete="address-line1"><span class="field-error"></span></label>
				<label class="field span-2"><span>Address line 2 <em class="tiny">(optional)</em></span><input class="input" name="Line2" autocomplete="address-line2"><span class="field-error"></span></label>
				<label class="field"><span>City</span><input class="input" name="City" required autocomplete="address-level2"><span class="field-error"></span></label>
				<label class="field"><span>State</span>
					<select class="select" name="State" required>
						<option>Kuala Lumpur</option>
						<option>Selangor</option>
						<option>Putrajaya</option>
						<option>Johor</option>
						<option>Kedah</option>
						<option>Kelantan</option>
						<option>Labuan</option>
						<option>Melaka</option>
						<option>Negeri Sembilan</option>
						<option>Pahang</option>
						<option>Penang</option>
						<option>Perak</option>
						<option>Perlis</option>
						<option>Sabah</option>
						<option>Sarawak</option>
						<option>Terengganu</option>
					</select><span class="field-error"></span></label>
			</div>
			<div class="form-actions">
				<button type="button" class="btn btn-ghost" id="address-back">Back</button>
				<button type="submit" class="btn btn-primary">Save &amp; deliver here</button>
			</div>
		</form>
	</div>
</div>
<?php layout_end(['pages/checkout.js']);
