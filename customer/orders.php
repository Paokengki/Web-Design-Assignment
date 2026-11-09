<?php
/** Order history with receipts — replaces sidebar/bills.php. */
require __DIR__ . '/../app/layout/master.php';

$user = require_customer();
$uid = (int) $user['User_ID'];
$orders = q_all('SELECT p.*, r.Name AS cafe, pr.Code AS promo_code FROM Payment p
	LEFT JOIN Restaurant r ON r.Restaurant_ID = p.Restaurant_ID
	LEFT JOIN Promotion pr ON pr.Promo_ID = p.Promo_ID
	WHERE p.User_ID = ? ORDER BY p.Created_at DESC', [$uid]);
$items = [];
if ($orders) {
	$ids = array_column($orders, 'Payment_ID');
	$marks = implode(',', array_fill(0, count($ids), '?'));
	foreach (q_all("SELECT * FROM Payment_Item WHERE Payment_ID IN ($marks) ORDER BY Payment_Item_ID", array_map('intval', $ids)) as $it) {
		$items[$it['Payment_ID']][] = $it;
	}
}
$paid = array_filter($orders, fn($o) => $o['Payment_status'] === 'SUCCEEDED');
$statusPill = ['SUCCEEDED' => ['pill-ok', 'Paid'], 'PENDING' => ['pill-warn', 'Pending'], 'FAILED' => ['pill-bad', 'Failed'], 'REFUNDED' => ['pill-muted', 'Refunded']];

layout_start(['title' => 'Orders', 'nav' => 'shop', 'active' => 'orders', 'styles' => ['pages/shop.css']]);
?>
<div class="page-head" data-reveal>
	<div><span class="eyebrow">History</span><h1>Your orders</h1><p>Tap an order to see the receipt.</p></div>
	<a class="btn btn-primary" href="<?= e(url('customer/home.php')) ?>">Order again</a>
</div>

<div class="stats">
	<div class="glass stat" data-reveal><small>Orders</small><strong data-count="<?= count($paid) ?>">0</strong><div class="stat-bar"></div></div>
	<div class="glass stat" style="--c:var(--violet)" data-reveal><small>Total spent</small><strong data-count="<?= array_sum(array_column($paid, 'Payment_amount')) ?>" data-prefix="RM" data-decimals="2">0</strong><div class="stat-bar"></div></div>
	<div class="glass stat" style="--c:var(--ok)" data-reveal><small>Saved with codes</small><strong data-count="<?= array_sum(array_column($paid, 'Discount_amount')) ?>" data-prefix="RM" data-decimals="2">0</strong><div class="stat-bar"></div></div>
</div>

<?php if (!$orders): ?>
	<div class="empty glass">No orders yet. <a class="grad-text" href="<?= e(url('customer/home.php')) ?>"><b>Find a café</b></a> and your receipts will show up here.</div>
<?php else: ?>
	<div class="receipts">
		<?php foreach ($orders as $o): [$pill, $label] = $statusPill[$o['Payment_status']] ?? ['pill-muted', $o['Payment_status']]; ?>
			<details class="receipt glass" data-reveal>
				<summary>
					<span class="receipt-icon"><?= icon('receipt') ?></span>
					<span class="receipt-main">
						<strong><?= e($o['cafe'] ?? 'Multiple cafés') ?></strong>
						<small>#<?= (int) $o['Payment_ID'] ?> · <?= e(date('d M Y, g:ia', strtotime($o['Created_at']))) ?> · <?= count($items[$o['Payment_ID']] ?? []) ?> items</small>
					</span>
					<span class="pill <?= $pill ?>"><span class="dot"></span><?= e($label) ?></span>
					<strong class="receipt-total"><?= money($o['Payment_amount']) ?></strong>
				</summary>
				<div class="receipt-body">
					<div class="summary-lines">
						<?php foreach ($items[$o['Payment_ID']] ?? [] as $it): ?>
							<div class="summary-line">
								<span><b><?= (int) $it['Quantity'] ?>×</b> <?= e($it['Item_name']) ?>
									<small><?= e(implode(' · ', array_filter([
										$it['Sugar_level'] ? 'Sugar ' . strtolower($it['Sugar_level']) : '',
										$it['Ice_level'] ? 'Ice ' . strtolower($it['Ice_level']) : '',
										$it['Remark'] ? '“' . $it['Remark'] . '”' : '',
									]))) ?></small></span>
								<span><?= money($it['Line_total']) ?></span>
							</div>
						<?php endforeach; ?>
					</div>
					<dl class="totals">
						<div><dt>Subtotal</dt><dd><?= money($o['Subtotal_amount']) ?></dd></div>
						<?php if ((float) $o['Discount_amount'] > 0): ?><div class="discount"><dt>Discount <?= $o['promo_code'] ? '<span class="pill pill-amber">' . e($o['promo_code']) . '</span>' : '' ?></dt><dd>−<?= money($o['Discount_amount']) ?></dd></div><?php endif; ?>
						<div><dt>SST 6%</dt><dd><?= money($o['SST_amount']) ?></dd></div>
						<div class="total"><dt>Total</dt><dd><?= money($o['Payment_amount']) ?></dd></div>
					</dl>
					<p class="tiny">Paid by <?= e($o['Payment_type'] ?: 'card') ?> via <?= e(ucfirst(strtolower($o['Provider']))) ?><?= $o['Paid_at'] ? ' · ' . e(date('d M Y, g:ia', strtotime($o['Paid_at']))) : '' ?></p>
				</div>
			</details>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
<?php layout_end();
