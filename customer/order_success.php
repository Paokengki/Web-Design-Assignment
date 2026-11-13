<?php
/** Order confirmation — replaces payment_cart/Payment_success.php. */
require __DIR__ . '/../app/layout/master.php';
require __DIR__ . '/../app/loyalty.php';
// Tracking: create a tracking row for this confirmed payment (idempotent, payment-gated).
require_once __DIR__ . '/../tracking/bootstrap.php';

$user = require_customer();
$id = (int) ($_GET['id'] ?? 0);
$order = q_one('SELECT p.*, r.Name AS cafe FROM Payment p LEFT JOIN Restaurant r ON r.Restaurant_ID = p.Restaurant_ID WHERE p.Payment_ID = ? AND p.User_ID = ?', [$id, (int) $user['User_ID']]);
if (!$order) redirect('customer/orders.php');
$items = q_all('SELECT * FROM Payment_Item WHERE Payment_ID = ?', [$id]);
$spent = (float) q_one("SELECT COALESCE(SUM(Payment_amount), 0) s FROM Payment WHERE User_ID = ? AND Payment_status = 'SUCCEEDED'", [(int) $user['User_ID']])['s'];
$loyalty = loyalty_for($spent);
// Ensure the tracking record exists (only if payment succeeded).
track_ensure_for_payment($id);

layout_start(['title' => 'Order confirmed', 'nav' => 'shop', 'styles' => ['pages/shop.css']]);
?>
<section class="success glass" data-reveal>
	<div class="success-mark" aria-hidden="true"><?= icon('check') ?></div>
	<span class="eyebrow">Order #<?= (int) $order['Payment_ID'] ?></span>
	<h1>Payment received.</h1>
	<p class="muted">Your order is with the kitchen. A receipt is in your <a class="grad-text" href="<?= e(url('customer/orders.php')) ?>"><b>orders</b></a>.</p>

	<div class="success-points">
		<span class="tiny">Points earned</span>
		<strong data-count="<?= (int) floor((float) $order['Payment_amount']) ?>" data-prefix="+">0</strong>
		<span class="tier tier-<?= e($loyalty['tier']) ?>"><?= e($loyalty['tier']) ?> · <?= number_format($loyalty['points']) ?> pts</span>
	</div>

	<div class="summary-lines">
		<?php foreach ($items as $it): ?>
			<div class="summary-line"><span><b><?= (int) $it['Quantity'] ?>×</b> <?= e($it['Item_name']) ?></span><span><?= money($it['Line_total']) ?></span></div>
		<?php endforeach; ?>
	</div>
	<dl class="totals">
		<div><dt>Subtotal</dt><dd><?= money($order['Subtotal_amount']) ?></dd></div>
		<?php if ((float) $order['Discount_amount'] > 0): ?><div class="discount"><dt>Discount</dt><dd>−<?= money($order['Discount_amount']) ?></dd></div><?php endif; ?>
		<div><dt>SST 6%</dt><dd><?= money($order['SST_amount']) ?></dd></div>
		<div class="total"><dt>Paid</dt><dd><?= money($order['Payment_amount']) ?></dd></div>
	</dl>
	<div class="success-actions">
		<a class="btn btn-primary" href="<?= e(url('tracking/customer/track.php?id=' . $id)) ?>">Track your order</a>
		<a class="btn btn-glass" href="<?= e(url('customer/home.php')) ?>">Order something else</a>
		<a class="btn btn-glass" href="<?= e(url('customer/dashboard.php')) ?>">My dashboard</a>
	</div>
</section>
<?php layout_end(['pages/success.js']);
