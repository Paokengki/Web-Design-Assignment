<?php
/**
 * Customer order tracking page — live map + status timeline.
 *
 * URL: tracking/customer/track.php?id=PAYMENT_ID
 *
 * Features:
 *   - Leaflet.js map with restaurant, destination, and courier markers
 *   - Real-time courier position via AJAX polling (5s; 1s in demo mode, driver animated along the route)
 *   - Cancel order (while Pending) with Stripe refund; Complete Order (once Delivered) credits points
 *   - Private chats with the restaurant, the driver and CafeDash support
 *   - Status timeline showing fulfillment progress
 *   - ETA countdown
 *   - Courier contact info (name, phone, plate)
 *   - Full order items (customer sees all items from all cafés)
 *
 * Auth: customer must be logged in and own the order.
 * Reads from Payment (READ ONLY) + Order_Tracking + Delivery_Location.
 * Cancel / Complete go through tracking/api/order_actions.php.
 */
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../config.php';

$user = require_customer();
$paymentId = (int) ($_GET['id'] ?? 0);
if ($paymentId <= 0) {
	flash('info', 'No order specified.');
	redirect('customer/orders.php');
}

// Verify the order belongs to this customer.
$payment = q_one(
	'SELECT Payment_ID, User_ID, Restaurant_ID, Payment_status, Payment_amount,
	        Created_at, Paid_at
	 FROM Payment WHERE Payment_ID = ? AND User_ID = ?',
	[$paymentId, (int) $user['User_ID']]
);
if (!$payment) {
	flash('info', 'Order not found.');
	redirect('customer/orders.php');
}

// Get tracking info (may not exist if migration hasn't been run or order predates tracking).
$tracking = q_one('SELECT * FROM Order_Tracking WHERE Payment_ID = ?', [$paymentId]);

require_once __DIR__ . '/../../app/services/chat.php';
$restaurant = null;
$cafeId = chat_order_cafe($paymentId); // multi-café orders: the first café handles the delivery
if ($cafeId) {
	$restaurant = q_one(
		'SELECT Name, Address, Latitude, Longitude FROM Restaurant WHERE Restaurant_ID = ?',
		[$cafeId]
	);
}

$GLOBALS['__layout_nav'] = 'shop';
require_once __DIR__ . '/../../app/layout/master.php';
layout_start([
	'title' => 'Track Order #' . $paymentId,
	'nav' => 'shop',
	'active' => 'orders',
	'styles' => ['pages/shop.css'],
]);
?>
<link rel="stylesheet" href="<?= e(asset('tracking/assets/css/tracking.css')) ?>">
<!-- Leaflet.js CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
	integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">

<div class="track-page">
	<a class="back-link" href="<?= e(url('customer/orders.php')) ?>"><?= '<svg viewBox="0 0 24 24" width="16" height="16"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>' ?>Back to orders</a>

	<?php if (!$tracking): ?>
		<!-- Tracking not available -->
		<div class="track-hero glass" data-reveal>
			<div class="track-hero-head">
				<div>
					<h1>Order #<?= (int) $paymentId ?></h1>
					<div class="track-meta">Placed <?= e(date('d M Y, g:ia', strtotime($payment['Created_at']))) ?></div>
				</div>
				<span class="track-pill <?= $payment['Payment_status'] === 'SUCCEEDED' ? 'track-delivered' : 'track-pending' ?>">
					<span class="dot"></span><?= e($payment['Payment_status'] === 'SUCCEEDED' ? 'Paid' : $payment['Payment_status']) ?>
				</span>
			</div>
			<p style="opacity:.7;font-size:14px;">
				Real-time tracking is not available for this order.
				Your order has been received and is being processed.
			</p>
			<a class="btn btn-primary" href="<?= e(url('customer/home.php')) ?>">Order something else</a>
		</div>
	<?php else: ?>
		<!-- Hero section -->
		<div class="track-hero glass" data-reveal>
			<div class="track-hero-head">
				<div>
					<h1>Order #<?= (int) $paymentId ?></h1>
					<div class="track-meta">
						<?= $restaurant ? e($restaurant['Name']) : 'Multiple cafés' ?>
						&middot; Placed <?= e(date('d M Y, g:ia', strtotime($payment['Created_at']))) ?>
						<?php if ($tracking['Tracking_PIN']): ?> &middot; PIN: <strong><?= e($tracking['Tracking_PIN']) ?></strong><?php endif; ?>
					</div>
				</div>
				<div id="track-status">
					<span class="track-pill <?= track_status_class($tracking['Order_status']) ?>">
						<span class="dot"></span><?= e(track_status_label($tracking['Order_status'])) ?>
					</span>
				</div>
			</div>
			<div class="track-eta" id="track-eta">
				<strong>--</strong>
				<div><small>Calculating...</small></div>
			</div>
		</div>

		<!-- Cancel (first 10 s) / Complete Order (once delivered) — filled by tracking-map.js -->
		<div class="track-actions glass" id="track-actions" aria-live="polite"></div>

		<!-- Map -->
		<div class="track-map-wrap" data-reveal>
			<div id="track-map"></div>
			<div class="track-map-loading" id="track-map-loading">Loading map...</div>
		</div>

		<!-- Courier info (hidden until courier is assigned) -->
		<div class="track-courier-card" id="track-courier" style="display:none;"></div>

		<!-- Private chats: restaurant / driver / CafeDash support -->
		<section class="order-chat glass" id="order-chat" data-payment="<?= (int) $paymentId ?>" data-reveal>
			<div class="order-chat-head">
				<div>
					<h3>Messages</h3>
					<small>Private chats about this order</small>
				</div>
			</div>
			<div class="order-chat-tabs" role="tablist">
				<button type="button" role="tab" class="is-active" data-party="restaurant">Restaurant<span class="chat-badge" hidden></span></button>
				<button type="button" role="tab" data-party="driver">Driver<span class="chat-badge" hidden></span></button>
				<button type="button" role="tab" data-party="admin">Support<span class="chat-badge" hidden></span></button>
			</div>
			<div class="order-chat-sub" id="chat-sub"></div>
			<div class="order-chat-log" id="chat-log" role="log" aria-live="polite"></div>
			<form class="order-chat-form" id="chat-form" autocomplete="off">
				<input type="text" name="body" maxlength="1000" placeholder="Write a message…" aria-label="Message">
				<button class="btn btn-primary btn-sm" type="submit">Send</button>
			</form>
		</section>

		<!-- Timeline -->
		<div class="track-timeline glass" data-reveal>
			<div id="track-timeline">
				<h3>Order Timeline</h3>
				<p style="font-size:13px;opacity:.6;">Loading status history...</p>
			</div>
		</div>

		<!-- Order items -->
		<div class="track-items glass" data-reveal>
			<div id="track-items">
				<h3>Order Items</h3>
				<p style="font-size:13px;opacity:.6;">Loading items...</p>
			</div>
		</div>

		<!-- Links -->
		<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;">
			<a class="btn btn-glass" href="<?= e(url('customer/home.php')) ?>">Order again</a>
			<a class="btn btn-glass" href="<?= e(url('customer/orders.php')) ?>">All orders</a>
		</div>
	<?php endif; ?>
</div>

<?php if ($tracking): ?>
<!-- Leaflet.js -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
	integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin></script>
<?php endif; ?>

<?php // page scripts load after the core (CD.api / CD.toast / CD.confirm)
layout_end($tracking ? ['tracking/assets/js/tracking-map.js', 'tracking/assets/js/order-chat.js'] : []);
