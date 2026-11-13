<?php
/**
 * Update order fulfillment status (Kitchen Staff, Manager, Courier).
 *
 * POST action = update_status
 *   Required: payment_id, new_status
 *   Optional: note, courier_id (Manager only)
 *
 * Role-based permissions (v4 parent/child design):
 *
 *   Kitchen Staff:  Updates CHILD Order_Tracking_Item.Item_status for their
 *                   assigned restaurant only. Valid child transitions:
 *                     Pending → Accepted → Preparing → Ready for Pickup
 *                   The PARENT Order_status is DERIVED from all children —
 *                   kitchen staff never set the parent status directly.
 *                   Parent becomes 'Ready for Pickup' only when ALL children
 *                   are 'Ready for Pickup'.
 *
 *   Manager / Super Admin:
 *                   Assigns courier on the PARENT row (→ Assigned).
 *                   Can only assign when ALL children are Ready for Pickup.
 *                   Can cancel any order (→ Cancelled).
 *
 *   Courier:        PARENT transitions only:
 *                     Assigned → Out for Delivery → Delivered
 *                   Only for their assigned deliveries.
 *                   On Delivered, all child rows are marked 'Completed'.
 *
 * This endpoint NEVER modifies Payment.Payment_status or any Stripe data.
 * Order_status (fulfillment) and Payment_status (payment) are separate.
 */
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../config.php';

$action = api_start();
$staff = current_staff();

if (!$staff) {
	json_out(false, null, 'Staff sign-in required.', 401);
}

if ($action !== 'update_status') json_out(false, null, 'Unknown action.', 400);

$paymentId  = (int) input('payment_id', '0');
$newStatus  = input('new_status');
$note       = input('note', '');
$courierId  = (int) input('courier_id', '0');  // used by Manager when assigning

if ($paymentId <= 0) json_out(false, null, 'payment_id is required.', 422);

// Validate the new status is a known parent value.
$validStatuses = [
	TRACK_STATUS_PENDING, TRACK_STATUS_ACCEPTED, TRACK_STATUS_PREPARING,
	TRACK_STATUS_READY, TRACK_STATUS_ASSIGNED, TRACK_STATUS_OUT_FOR_DELIVERY,
	TRACK_STATUS_DELIVERED, TRACK_STATUS_CANCELLED,
];
if (!in_array($newStatus, $validStatuses, true)) {
	json_out(false, null, 'Invalid status.', 422);
}

// Get the parent tracking row.
$tracking = q_one('SELECT * FROM Order_Tracking WHERE Payment_ID = ?', [$paymentId]);
if (!$tracking) json_out(false, null, 'Tracking record not found.', 404);

$currentStatus = $tracking['Order_status'];
$role          = $staff['Role'];
$staffId       = (int) $staff['Staff_ID'];

// Check terminal state — no changes allowed.
if (in_array($currentStatus, TRACK_STATUS_TERMINAL)) {
	json_out(false, null, 'This order is already ' . track_status_label($currentStatus) . '.', 422);
}

// ── Get all child rows for this order ────────────────────────────────────────
$children = q_all(
	'SELECT * FROM Order_Tracking_Item WHERE Payment_ID = ? ORDER BY Restaurant_ID',
	[$paymentId]
);

// ── Role-based permission check and execution ───────────────────────────────
$allowed = false;

if ($role === 'Kitchen Staff') {
	// Kitchen Staff update their restaurant's CHILD row only.
	// Valid child transitions: Pending → Accepted → Preparing → Ready for Pickup
	if (!in_array($newStatus, [TRACK_STATUS_ACCEPTED, TRACK_STATUS_PREPARING, TRACK_STATUS_READY], true)) {
		json_out(false, null, 'Kitchen staff can only accept, prepare, or mark orders ready.', 422);
	}

	// The new_status from the client is a parent-level status string. For kitchen staff,
	// it maps directly to the child Item_status (they share the same ENUM values for
	// Pending/Accepted/Preparing/Ready for Pickup).
	$newChildStatus = $newStatus; // 'Accepted', 'Preparing', or 'Ready for Pickup'

	// Find the kitchen staff's assigned restaurant.
	$assignedRest = (int) ($staff['Assigned_Restaurant_ID'] ?? 0);
	if ($assignedRest <= 0) {
		json_out(false, null, 'You are not assigned to a café. Contact a manager.', 403);
	}

	// Find the child row for this kitchen staff's restaurant.
	$myChild = null;
	foreach ($children as $child) {
		if ((int) $child['Restaurant_ID'] === $assignedRest) {
			$myChild = $child;
			break;
		}
	}

	if (!$myChild) {
		// This order has no items from the kitchen staff's assigned café.
		json_out(false, null, 'This order does not belong to your assigned café.', 403);
	}

	// Validate the child transition is allowed.
	$currentChildStatus = $myChild['Item_status'];
	$validChildNext = track_item_next_statuses($currentChildStatus);
	if (!in_array($newChildStatus, $validChildNext, true)) {
		json_out(false, null, 'Cannot change from ' . track_status_label($currentChildStatus) . ' to ' . track_status_label($newChildStatus) . ' for your café.', 422);
	}

	// All checks passed — perform the child update + parent derivation.
	db()->begin_transaction();
	try {
		// Update the child row.
		q(
			'UPDATE Order_Tracking_Item SET Item_status = ?, Updated_by = ?, Updated_at = NOW() WHERE Item_Tracking_ID = ?',
			[$newChildStatus, $staffId, (int) $myChild['Item_Tracking_ID']]
		);

		// Log the child-level status change.
		q(
			'INSERT INTO Order_Status_Log (Payment_ID, Restaurant_ID, Order_status, Log_Level, Changed_By, Note)
			 VALUES (?, ?, ?, ?, ?, ?)',
			[$paymentId, $assignedRest, $newChildStatus, 'item', $staffId, $note ?: track_status_label($newChildStatus)]
		);

		// Derive the new parent status from all children.
		// Re-fetch children to get the updated statuses.
		$updatedChildren = q_all(
			'SELECT Item_status FROM Order_Tracking_Item WHERE Payment_ID = ?',
			[$paymentId]
		);
		$childStatuses = array_column($updatedChildren, 'Item_status');
		$derivedParent = track_derive_parent_status($childStatuses, $currentStatus);

		// Update the parent if the derived status differs from the current status.
		if ($derivedParent !== $currentStatus) {
			q(
				'UPDATE Order_Tracking SET Order_status = ?, Updated_at = NOW() WHERE Payment_ID = ?',
				[$derivedParent, $paymentId]
			);

			// Log the parent-level status change (derived).
			q(
				'INSERT INTO Order_Status_Log (Payment_ID, Order_status, Log_Level, Changed_By, Note)
				 VALUES (?, ?, ?, NULL, ?)',
				[$paymentId, $derivedParent, 'parent', 'Status derived from all café preparations']
			);
		}

		db()->commit();
	} catch (Throwable $e) {
		db()->rollback();
		error_log('Tracking update_status (kitchen) error: ' . $e->getMessage());
		json_out(false, null, 'Failed to update order status.', 500);
	}

	// Return the updated tracking row + child info.
	$updated = q_one('SELECT * FROM Order_Tracking WHERE Payment_ID = ?', [$paymentId]);
	$updatedChild = q_one('SELECT * FROM Order_Tracking_Item WHERE Item_Tracking_ID = ?', [(int) $myChild['Item_Tracking_ID']]);
	json_out(true, [
		'payment_id'        => $paymentId,
		'order_status'      => $updated['Order_status'],
		'order_status_label' => track_status_label($updated['Order_status']),
		'child_status'      => $updatedChild['Item_status'],
		'child_status_label' => track_status_label($updatedChild['Item_status']),
		'restaurant_id'     => (int) $updatedChild['Restaurant_ID'],
		'updated_at'        => $updated['Updated_at'],
	]);

} elseif ($role === 'Manager' || $role === 'Super Admin') {
	// Manager/Super Admin operate on the PARENT row.
	if ($newStatus === TRACK_STATUS_ASSIGNED) {
		// Assign courier — verify all children are Ready for Pickup first.
		if (!track_can_advance_parent(TRACK_STATUS_ASSIGNED, array_column($children, 'Item_status'))) {
			json_out(false, null, 'Cannot assign courier until all cafés have marked their orders as Ready for Pickup.', 422);
		}

		// Validate parent transition.
		$nextStatuses = track_next_statuses($currentStatus);
		if (!in_array(TRACK_STATUS_ASSIGNED, $nextStatuses, true)) {
			json_out(false, null, 'Cannot assign courier from ' . track_status_label($currentStatus) . '.', 422);
		}

		if ($courierId <= 0) {
			json_out(false, null, 'Select a courier to assign.', 422);
		}
		// Verify the courier exists and has the Courier role.
		$courier = q_one(
			"SELECT Staff_ID FROM Staff WHERE Staff_ID = ? AND Role = 'Courier' AND Status = 'Active'",
			[$courierId]
		);
		if (!$courier) {
			json_out(false, null, 'Selected courier is not available.', 422);
		}
		$allowed = true;

	} elseif ($newStatus === TRACK_STATUS_CANCELLED) {
		// Cancel — always allowed for Manager/Super Admin.
		// Validate parent transition allows cancellation from current status.
		$nextStatuses = track_next_statuses($currentStatus);
		if (!in_array(TRACK_STATUS_CANCELLED, $nextStatuses, true)) {
			json_out(false, null, 'Cannot cancel an order that is ' . track_status_label($currentStatus) . '.', 422);
		}
		$allowed = true;

	} else {
		json_out(false, null, 'Managers can only assign couriers or cancel orders.', 422);
	}

} elseif ($role === 'Courier') {
	// Courier operates on the PARENT row: Out for Delivery, Delivered.
	if (!in_array($newStatus, [TRACK_STATUS_OUT_FOR_DELIVERY, TRACK_STATUS_DELIVERED], true)) {
		json_out(false, null, 'Couriers can only mark orders as out for delivery or delivered.', 422);
	}

	if ((int) $tracking['Courier_ID'] !== $staffId) {
		json_out(false, null, 'You are not assigned to this delivery.', 403);
	}

	// Validate parent transition.
	$nextStatuses = track_next_statuses($currentStatus);
	if (!in_array($newStatus, $nextStatuses, true)) {
		json_out(false, null, 'Cannot change from ' . track_status_label($currentStatus) . ' to ' . track_status_label($newStatus) . '.', 422);
	}

	$allowed = true;

} else {
	json_out(false, null, 'Your role cannot perform this action.', 403);
}

if (!$allowed) {
	json_out(false, null, 'Your role cannot perform this status change.', 403);
}

// ── Perform parent-level update (Manager / Courier) ──────────────────────────
db()->begin_transaction();
try {
	if ($newStatus === TRACK_STATUS_ASSIGNED && $courierId > 0) {
		// Manager assigning courier — set Courier_ID and Assigned_By.
		q(
			'UPDATE Order_Tracking SET Order_status = ?, Courier_ID = ?, Assigned_By = ?, Updated_at = NOW() WHERE Payment_ID = ?',
			[$newStatus, $courierId, $staffId, $paymentId]
		);
	} else {
		q(
			'UPDATE Order_Tracking SET Order_status = ?, Updated_at = NOW() WHERE Payment_ID = ?',
			[$newStatus, $paymentId]
		);
	}

	// Log the parent-level status change.
	q(
		'INSERT INTO Order_Status_Log (Payment_ID, Order_status, Log_Level, Changed_By, Note)
		 VALUES (?, ?, ?, ?, ?)',
		[$paymentId, $newStatus, 'parent', $staffId, $note ?: track_status_label($newStatus)]
	);

	// If Delivered, mark all child rows as Completed.
	if ($newStatus === TRACK_STATUS_DELIVERED) {
		q(
			'UPDATE Order_Tracking_Item SET Item_status = ?, Updated_by = ?, Updated_at = NOW()
			 WHERE Payment_ID = ? AND Item_status != ?',
			[TRACK_ITEM_COMPLETED, $staffId, $paymentId, TRACK_ITEM_COMPLETED]
		);
	}

	// If Cancelled, log a cancellation note for each child that wasn't Completed.
	if ($newStatus === TRACK_STATUS_CANCELLED) {
		$activeChildren = q_all(
			'SELECT Restaurant_ID FROM Order_Tracking_Item WHERE Payment_ID = ? AND Item_status != ?',
			[$paymentId, TRACK_ITEM_COMPLETED]
		);
		foreach ($activeChildren as $ac) {
			q(
				'INSERT INTO Order_Status_Log (Payment_ID, Restaurant_ID, Order_status, Log_Level, Changed_By, Note)
				 VALUES (?, ?, ?, ?, ?, ?)',
				[$paymentId, (int) $ac['Restaurant_ID'], TRACK_STATUS_CANCELLED, 'item', $staffId, 'Order cancelled by management']
			);
		}
	}

	db()->commit();
} catch (Throwable $e) {
	db()->rollback();
	error_log('Tracking update_status (manager/courier) error: ' . $e->getMessage());
	json_out(false, null, 'Failed to update order status.', 500);
}

// Return the updated tracking row.
$updated = q_one('SELECT * FROM Order_Tracking WHERE Payment_ID = ?', [$paymentId]);
json_out(true, [
	'payment_id'        => $paymentId,
	'order_status'      => $updated['Order_status'],
	'order_status_label' => track_status_label($updated['Order_status']),
	'courier_id'        => $updated['Courier_ID'] !== null ? (int) $updated['Courier_ID'] : null,
	'updated_at'        => $updated['Updated_at'],
]);
