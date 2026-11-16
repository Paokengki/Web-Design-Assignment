<?php
/**
 * Checkout (AJAX). Signed-in customers only.
 * POST action = session → { url } — Stripe's hosted payment page for the current cart
 *                         (or straight to the receipt when a 100% voucher makes the order free).
 * The return trip is handled by customer/checkout_return.php.
 */
require __DIR__ . '/../../app/bootstrap.php';
require __DIR__ . '/../../app/services/payments.php';

$action = api_start();
$user = require_customer();

try {
	if ($action !== 'session') json_out(false, null, 'Unknown action.', 400);
	// A 100% voucher brings the total to RM0: place the order directly, no card needed.
	$sum = cart_summary((int) $user['User_ID']);
	if ($sum['items'] && $sum['promo'] && $sum['total'] <= 0) {
		$id = payment_place_free_order($user);
		json_out(true, ['url' => url('customer/order_success.php?id=' . $id), 'free' => true]);
	}
	json_out(true, ['url' => payment_create_session($user)]);
} catch (InvalidArgumentException $e) {
	json_out(false, null, $e->getMessage(), 422);
} catch (RuntimeException $e) {
	json_out(false, null, $e->getMessage(), 503);
} catch (\Stripe\Exception\ApiErrorException $e) {
	error_log('Stripe: ' . $e->getMessage());
	json_out(false, null, 'Stripe: ' . $e->getMessage(), 502);
}
