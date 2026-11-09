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
$address = q_one('SELECT * FROM User_Address WHERE User_ID = ? ORDER BY Is_default DESC, Created_at DESC LIMIT 1', [(int) $user['User_ID']]);
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
			<div class="card-title"><h3>Deliver to</h3><a class="btn btn-sm btn-ghost" href="<?= e(url('customer/dashboard.php')) ?>">Manage</a></div>
			<?php if ($address): ?>
				<p class="address-line"><?= icon('pin') ?><span><strong><?= e($address['Label']) ?> · <?= e($address['Recipient']) ?></strong><br>
				<span class="muted"><?= e($address['Line1']) ?><?= $address['Line2'] ? ', ' . e($address['Line2']) : '' ?>, <?= e($address['Postcode']) ?> <?= e($address['City']) ?> · <?= e($address['Phone']) ?></span></span></p>
			<?php else: ?>
				<div class="flash">No delivery address yet. <a class="grad-text" href="<?= e(url('customer/dashboard.php')) ?>"><b>Add one in your dashboard</b></a> before paying.</div>
			<?php endif; ?>
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
				<?php if (str_starts_with($stripe['publishable_key'], 'pk_test_')): ?>
					<p class="test-note"><span class="pill pill-warn">Test mode</span> Use card <span class="mono">4242 4242 4242 4242</span>, any future date, any CVC. No real money moves.</p>
				<?php endif; ?>
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
		<button class="btn btn-primary btn-lg btn-block" id="pay-btn" <?= (!$stripe || !$address) ? 'disabled' : '' ?> data-magnetic>
			<?= icon('lock') ?>Pay <span id="pay-amount"><?= money($sum['total']) ?></span>
		</button>
		<p class="tiny center">You’ll earn about <b id="earn-points"><?= (int) floor($sum['total']) ?></b> loyalty points.</p>
	</aside>
</div>
<script type="application/json" id="cart-initial"><?= json_encode($sum, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php layout_end(['pages/checkout.js']);
