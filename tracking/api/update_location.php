<?php
/**
 * Update courier GPS location (Courier only).
 *
 * POST action = update_location
 *   Required: payment_id, latitude, longitude
 *   Optional: accuracy
 *
 * Only the courier assigned to the delivery can update their position.
 * Writes to:
 *   - Delivery_Location (history row for path trail)
 *   - Order_Tracking.Courier_Latitude / Courier_Longitude (latest position)
 *
 * Never touches Payment or Stripe data.
 */
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../config.php';

$action = api_start();
$staff = current_staff();

if (!$staff) {
	json_out(false, null, 'Staff sign-in required.', 401);
}

if ($action !== 'update_location') json_out(false, null, 'Unknown action.', 400);

// Only Couriers can update GPS.
if ($staff['Role'] !== 'Courier') {
	json_out(false, null, 'Only couriers can update delivery location.', 403);
}

$paymentId = (int) input('payment_id', '0');
$latitude  = (float) input('latitude', '0');
$longitude = (float) input('longitude', '0');
$accuracy  = input('accuracy', '');
$accuracyFloat = $accuracy !== '' ? (float) $accuracy : null;

if ($paymentId <= 0) json_out(false, null, 'payment_id is required.', 422);
if ($latitude == 0 && $longitude == 0) json_out(false, null, 'Valid coordinates required.', 422);

// Verify this courier is assigned to this delivery.
$tracking = q_one('SELECT * FROM Order_Tracking WHERE Payment_ID = ?', [$paymentId]);
if (!$tracking) json_out(false, null, 'Delivery not found.', 404);

if ((int) $tracking['Courier_ID'] !== (int) $staff['Staff_ID']) {
	json_out(false, null, 'You are not assigned to this delivery.', 403);
}

// Only track during active delivery (Assigned or Out for Delivery).
if (!in_array($tracking['Order_status'], [TRACK_STATUS_ASSIGNED, TRACK_STATUS_OUT_FOR_DELIVERY])) {
	json_out(false, ['active' => false], 'Delivery is not active.', 200);
}

db()->begin_transaction();
try {
	// Insert a history row for the path trail.
	q(
		'INSERT INTO Delivery_Location (Payment_ID, Courier_ID, Latitude, Longitude, Accuracy) VALUES (?, ?, ?, ?, ?)',
		[$paymentId, (int) $staff['Staff_ID'], $latitude, $longitude, $accuracyFloat]
	);

	// Update the latest position on the tracking row.
	q(
		'UPDATE Order_Tracking SET Courier_Latitude = ?, Courier_Longitude = ?, Updated_at = NOW() WHERE Payment_ID = ?',
		[$latitude, $longitude, $paymentId]
	);

	db()->commit();
} catch (Throwable $e) {
	db()->rollback();
	error_log('Tracking update_location error: ' . $e->getMessage());
	json_out(false, null, 'Failed to update location.', 500);
}

json_out(true, [
	'payment_id' => $paymentId,
	'latitude'   => $latitude,
	'longitude'  => $longitude,
	'updated_at' => date('Y-m-d H:i:s'),
]);
