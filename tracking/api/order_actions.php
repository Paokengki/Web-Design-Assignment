<?php
/**
 * Customer order actions (AJAX). Signed-in customer, own orders only.
 *
 * POST action = cancel   (payment_id) → only while the order is still Pending (the 10-second
 *                                        "Accept Order" window). Stops the lifecycle, refunds the card
 *                                        through Stripe, returns any voucher, frees the driver slot.
 *      action = complete (payment_id) → only once Delivered. Marks the order complete, which is
 *                                        when its loyalty points are credited (app/loyalty.php).
 */
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../demo.php';
require_once __DIR__ . '/../../app/services/payments.php';
require_once __DIR__ . '/../../app/loyalty.php';

$action = api_start();
$user = require_customer();
$uid = (int) $user['User_ID'];
$pid = (int) input('payment_id');

// Bring the order up to date first: if the 10 seconds have passed, the restaurant has already accepted.
track_demo_tick($pid);

/** Give back the voucher an order used, so a cancelled order costs the customer nothing. */
function return_order_voucher(array $payment): void
{
	if (!$payment['Promo_ID']) return;
	$promoId = (int) $payment['Promo_ID'];
	$pid = (int) $payment['Payment_ID'];
	$uv = q_one('SELECT User_Voucher_ID FROM User_Voucher WHERE Used_payment_id = ?', [$pid]);
	if ($uv) {
		// points reward: becomes unused again (its stock was counted when redeemed, so no counter change)
		q('UPDATE User_Voucher SET Used_at = NULL, Used_payment_id = NULL WHERE User_Voucher_ID = ?', [(int) $uv['User_Voucher_ID']]);
	} else {
		// free code: one fewer use, and the customer may use it again
		q('UPDATE Promotion SET Redemption_count = GREATEST(Redemption_count - 1, 0) WHERE Promo_ID = ?', [$promoId]);
	}
	q('DELETE FROM Promotion_Redemption WHERE Payment_ID = ?', [$pid]);
}

switch ($action) {
	case 'cancel':
		$db = db();
		$db->begin_transaction();
		try {
			$p = q_one('SELECT * FROM Payment WHERE Payment_ID = ? AND User_ID = ? FOR UPDATE', [$pid, $uid]);
			$t = q_one('SELECT * FROM Order_Tracking WHERE Payment_ID = ? FOR UPDATE', [$pid]);
			if (!$p || !$t) throw new InvalidArgumentException('Order not found.');
			if ($t['Order_status'] === TRACK_STATUS_CANCELLED) throw new InvalidArgumentException('This order is already cancelled.');
			if ($t['Order_status'] !== TRACK_STATUS_PENDING) throw new InvalidArgumentException('Too late to cancel — the restaurant has already accepted your order.');

			// 1. Refund the card first: if Stripe refuses, nothing is cancelled.
			$refundId = null;
			$refunded = 0.0;
			if ($p['Provider'] === 'STRIPE' && (float) $p['Payment_amount'] > 0) {
				if (!stripe_ready()) throw new RuntimeException('Refunds need Stripe, which isn’t set up on this server.');
				$refund = \Stripe\Refund::create(['payment_intent' => $p['Provider_payment_id'], 'reason' => 'requested_by_customer']);
				$refundId = $refund->id;
				$refunded = round($refund->amount / 100, 2);
			}

			// 2. Cancel the order and record the refund.
			q("UPDATE Payment SET Payment_status = 'REFUNDED', Refund_id = ?, Refund_amount = ?, Refunded_at = NOW() WHERE Payment_ID = ?", [$refundId, $refunded, $pid]);
			track_apply_status($t, TRACK_STATUS_CANCELLED, time(), $refunded > 0 ? 'Cancelled by customer — RM' . number_format($refunded, 2) . ' refunded' : 'Cancelled by customer');
			q('UPDATE Order_Tracking SET Courier_ID = NULL WHERE Tracking_ID = ?', [(int) $t['Tracking_ID']]); // no driver held
			return_order_voucher($p);
			$db->commit();
		} catch (InvalidArgumentException $e) {
			$db->rollback();
			json_out(false, null, $e->getMessage(), 409);
		} catch (\Stripe\Exception\ApiErrorException $e) {
			$db->rollback();
			error_log('Stripe refund #' . $pid . ': ' . $e->getMessage());
			json_out(false, null, 'The refund could not be processed: ' . $e->getMessage(), 502);
		} catch (RuntimeException $e) {
			$db->rollback();
			json_out(false, null, $e->getMessage(), 503);
		}

		try {
			chat_auto($pid, 'restaurant', 'Your order has been cancelled' . ($refunded > 0 ? ' and RM' . number_format($refunded, 2) . ' is on its way back to your card' : '') . '. Hope to see you again soon!');
		} catch (Throwable $e) { /* the cancellation itself already succeeded */ }

		json_out(true, [
			'status' => TRACK_STATUS_CANCELLED,
			'refunded' => $refunded,
			'refund_id' => $refundId,
			'message' => $refunded > 0
				? 'Order cancelled. RM' . number_format($refunded, 2) . ' has been refunded to your card (usually 5–10 working days to appear).'
				: 'Order cancelled. Your voucher has been returned to your account.',
		]);

	case 'complete':
		$db = db();
		$db->begin_transaction();
		$p = q_one('SELECT * FROM Payment WHERE Payment_ID = ? AND User_ID = ? FOR UPDATE', [$pid, $uid]);
		$t = $p ? q_one('SELECT Order_status FROM Order_Tracking WHERE Payment_ID = ?', [$pid]) : null;
		if (!$p || !$t) { $db->rollback(); json_out(false, null, 'Order not found.', 404); }
		if ($t['Order_status'] !== TRACK_STATUS_DELIVERED) { $db->rollback(); json_out(false, null, 'You can complete the order once it has been delivered.', 409); }
		if ($p['Completed_at'] !== null) { $db->rollback(); json_out(false, null, 'This order is already completed.', 409); }
		q('UPDATE Payment SET Completed_at = NOW() WHERE Payment_ID = ?', [$pid]);
		$db->commit();
		$earned = $p['Points_rule'] === 'on_complete' && $p['Payment_status'] === 'SUCCEEDED' ? points_for_amount((float) $p['Payment_amount']) : 0;
		json_out(true, [
			'completed' => true,
			'points' => $earned,
			'balance' => points_balance($uid),
			'message' => $earned > 0 ? "Order completed — +{$earned} points added to your balance." : 'Order completed. Thank you!',
		]);

	default:
		json_out(false, null, 'Unknown action.', 400);
}
