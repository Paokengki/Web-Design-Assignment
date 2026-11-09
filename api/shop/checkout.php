<?php
/**
 * Checkout (AJAX). Signed-in customers only.
 * POST action = session → { url } — Stripe's hosted payment page for the current cart.
 * The return trip is handled by customer/checkout_return.php.
 */
require __DIR__ . '/../../app/bootstrap.php';
require __DIR__ . '/../../app/services/payments.php';

$action = api_start();
$user = require_customer();

try {
	if ($action !== 'session') json_out(false, null, 'Unknown action.', 400);
	json_out(true, ['url' => payment_create_session($user)]);
} catch (InvalidArgumentException $e) {
	json_out(false, null, $e->getMessage(), 422);
} catch (RuntimeException $e) {
	json_out(false, null, $e->getMessage(), 503);
} catch (\Stripe\Exception\ApiErrorException $e) {
	error_log('Stripe: ' . $e->getMessage());
	json_out(false, null, 'Stripe: ' . $e->getMessage(), 502);
}
