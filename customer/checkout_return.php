<?php
/** Stripe sends customers here after paying. We verify with Stripe, record the order, then show the receipt. */
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/services/payments.php';

$user = require_customer();
try {
	$id = payment_finalize_session($user, (string) ($_GET['session_id'] ?? ''));
	redirect('customer/order_success.php?id=' . $id);
} catch (InvalidArgumentException $e) {
	flash('error', $e->getMessage());
} catch (Throwable $e) {
	error_log('CafeDash checkout return: ' . $e->getMessage());
	flash('error', 'We couldn’t confirm your payment just now. If you were charged, it will appear in Orders shortly — or contact us.');
}
redirect('customer/checkout.php');
