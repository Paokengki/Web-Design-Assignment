<?php
/**
 * Tracking bootstrap — included at the top of customer/order_success.php.
 *
 * Creates tracking records for a confirmed payment:
 *   1. One PARENT Order_Tracking row (overall order status, customer-facing).
 *   2. One CHILD Order_Tracking_Item row per distinct restaurant in the order.
 *   3. An initial Order_Status_Log entry (Log_Level = 'parent').
 *
 * Conditions (all must be true):
 *   - The Payment exists in the database.
 *   - Payment_status = 'SUCCEEDED' (payment confirmed by Stripe).
 *   - No duplicate parent or child records are created (idempotent).
 *
 * Multi-restaurant support:
 *   The restaurant list is resolved via Payment_Item → Food → Restaurant_ID.
 *   For single-café orders, Payment.Restaurant_ID is set and one child is created.
 *   For multi-café orders, Payment.Restaurant_ID is NULL and N children are created.
 *
 * This file does NOT modify any existing Payment, Payment_Item, Food, or Stripe data.
 * It only READS from Payment/Payment_Item/Food/User_Address and WRITES to the new
 * Order_Tracking / Order_Tracking_Item / Order_Status_Log tables.
 *
 * Also resolves the delivery address and destination coordinates:
 *   - Uses the user's default User_Address.
 *   - If User_Address has lat/lng, uses those.
 *   - Otherwise, leaves lat/lng NULL (customer can set them on the tracking page).
 */
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/config.php';

/**
 * Ensure tracking records exist for a confirmed (SUCCEEDED) payment.
 *
 * Creates one parent Order_Tracking row + N child Order_Tracking_Item rows
 * (one per distinct restaurant) in a single transaction. Idempotent: if the
 * parent already exists, only missing children are created.
 *
 * @param int $paymentId  The Payment_ID to create tracking for.
 * @return array|null     The parent tracking row, or null if payment not confirmed.
 */
function track_ensure_for_payment(int $paymentId): ?array
{
	// ── 1. Read the payment row (only READ from existing Payment table) ────────
	$payment = q_one(
		'SELECT p.Payment_ID, p.User_ID, p.Restaurant_ID, p.Payment_status, p.Created_at
		 FROM Payment p
		 WHERE p.Payment_ID = ?',
		[$paymentId]
	);

	if (!$payment) return null;

	// CRITICAL: Only create tracking for confirmed payments.
	// This preserves the separation between Payment_status (Stripe) and Order_status (tracking).
	if ($payment['Payment_status'] !== 'SUCCEEDED') return null;

	// ── 2. Idempotency: check if parent tracking row already exists ────────────
	$existing = q_one('SELECT * FROM Order_Tracking WHERE Payment_ID = ?', [$paymentId]);

	$userId       = (int) $payment['User_ID'];
	$restaurantId = $payment['Restaurant_ID'] !== null ? (int) $payment['Restaurant_ID'] : null;
	$pin          = track_generate_pin();
	$estMinutes   = TRACK_DEFAULT_PREP_MINUTES + TRACK_DEFAULT_DELIVERY_MINUTES;

	// ── 3. Resolve delivery address + destination coordinates ──────────────────
	$address = q_one(
		'SELECT * FROM User_Address WHERE User_ID = ? ORDER BY Is_default DESC, Created_at DESC LIMIT 1',
		[$userId]
	);

	$addressId = $address ? (int) $address['Address_ID'] : null;
	$destLat   = ($address && $address['Latitude'] !== null) ? (float) $address['Latitude'] : null;
	$destLng   = ($address && $address['Longitude'] !== null) ? (float) $address['Longitude'] : null;

	// ── 4. Resolve distinct restaurants in this order ──────────────────────────
	// Chain: Payment_Item.Food_ID → Food.Restaurant_ID → Restaurant.Restaurant_ID
	// This works for both single-café and multi-café orders.
	$restaurants = q_all(
		'SELECT DISTINCT f.Restaurant_ID
		 FROM Payment_Item pi
		 INNER JOIN Food f ON f.Food_ID = pi.Food_ID
		 WHERE pi.Payment_ID = ? AND f.Restaurant_ID IS NOT NULL
		 ORDER BY f.Restaurant_ID',
		[$paymentId]
	);

	// If no restaurants could be resolved (e.g., all Food_IDs are NULL), fall back
	// to Payment.Restaurant_ID if it is set. This is a safety net — under normal
	// operation every Payment_Item has a valid Food_ID pointing to a Food with a Restaurant_ID.
	if (empty($restaurants) && $restaurantId !== null) {
		$restaurants = [['Restaurant_ID' => $restaurantId]];
	}

	// ── 5. Create parent + children in a single transaction ────────────────────
	// If the parent already exists, we still ensure all children exist (covers the
	// case where the parent was created by an older version of this function).
	db()->begin_transaction();
	try {
		if (!$existing) {
			// Create the parent tracking row.
			// INSERT IGNORE provides extra idempotency safety (the Payment_ID UNIQUE
			// constraint would reject a duplicate, but INSERT IGNORE suppresses the error).
			q(
				'INSERT IGNORE INTO Order_Tracking
					(Payment_ID, User_ID, Restaurant_ID, Order_status, Delivery_Address_ID,
					 Dest_Latitude, Dest_Longitude, Estimated_minutes, Tracking_PIN)
				 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
				[
					$paymentId, $userId, $restaurantId,
					TRACK_STATUS_PENDING,
					$addressId,
					$destLat,
					$destLng,
					$estMinutes,
					$pin,
				]
			);

			// Log the initial parent status.
			// Log_Level = 'parent' marks this as an overall order event.
			q(
				'INSERT INTO Order_Status_Log (Payment_ID, Order_status, Log_Level, Changed_By, Note)
				 VALUES (?, ?, ?, NULL, ?)',
				[$paymentId, TRACK_STATUS_PENDING, 'parent', 'Order placed by customer']
			);
		}

		// Fetch the tracking ID (whether we just created it or it already existed).
		$tracking = q_one('SELECT * FROM Order_Tracking WHERE Payment_ID = ?', [$paymentId]);
		if (!$tracking) {
			db()->rollback();
			return null;
		}
		$trackingId = (int) $tracking['Tracking_ID'];

		// Create child rows (one per restaurant) — idempotent via INSERT IGNORE.
		// The UNIQUE KEY uk_item_per_restaurant (Payment_ID, Restaurant_ID) prevents duplicates.
		foreach ($restaurants as $r) {
			$childRestId = (int) $r['Restaurant_ID'];
			q(
				'INSERT IGNORE INTO Order_Tracking_Item
					(Tracking_ID, Payment_ID, Restaurant_ID, Item_status)
				 VALUES (?, ?, ?, ?)',
				[$trackingId, $paymentId, $childRestId, TRACK_ITEM_PENDING]
			);
		}

		db()->commit();
	} catch (Throwable $e) {
		db()->rollback();
		error_log('Tracking track_ensure_for_payment error: ' . $e->getMessage());
		return $existing ?: null;
	}

	// ── 6. Return the parent tracking row ───────────────────────────────────────
	return q_one('SELECT * FROM Order_Tracking WHERE Payment_ID = ?', [$paymentId]);
}
