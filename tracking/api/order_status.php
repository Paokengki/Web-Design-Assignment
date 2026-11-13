<?php
/**
 * GET order tracking data for customers.
 *
 * POST action = get → returns tracking info for a Payment_ID.
 *   Required: payment_id
 *   Auth: customer must own the order (User_ID match).
 *
 * Returns: tracking row, status history, latest courier position, restaurant info,
 *          order items (filtered to this customer's full order).
 *
 * This endpoint READS ONLY from existing + tracking tables. It never writes to Payment.
 */
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../config.php';

$action = api_start();
$user = require_customer();

if ($action !== 'get') json_out(false, null, 'Unknown action.', 400);

$paymentId = (int) input('payment_id', '0');
if ($paymentId <= 0) json_out(false, null, 'payment_id is required.', 422);

// Verify the order belongs to this customer — security check.
$payment = q_one(
	'SELECT Payment_ID, User_ID, Restaurant_ID, Payment_status, Payment_amount,
	        Subtotal_amount, SST_amount, Discount_amount, Created_at, Paid_at,
	        Provider, Payment_type
	 FROM Payment WHERE Payment_ID = ? AND User_ID = ?',
	[$paymentId, (int) $user['User_ID']]
);

if (!$payment) json_out(false, null, 'Order not found.', 404);

// Get the tracking row.
$tracking = q_one('SELECT * FROM Order_Tracking WHERE Payment_ID = ?', [$paymentId]);

if (!$tracking) {
	// Tracking row doesn't exist yet — return minimal data.
	json_out(true, [
		'payment_id'   => $paymentId,
		'has_tracking' => false,
		'payment_status' => $payment['Payment_status'],
		'message'      => 'Tracking is not available for this order yet.',
	]);
}

// Get restaurant info with coordinates.
$restaurant = null;
if ($payment['Restaurant_ID']) {
	$restaurant = q_one(
		'SELECT Restaurant_ID, Name, Address, Latitude, Longitude FROM Restaurant WHERE Restaurant_ID = ?',
		[(int) $payment['Restaurant_ID']]
	);
}

// Get order items (full order for customer view).
$items = q_all(
	'SELECT Item_name, Item_type, Unit_amount, Quantity, Line_total, Sugar_level, Ice_level, Remark
	 FROM Payment_Item WHERE Payment_ID = ? ORDER BY Payment_Item_ID',
	[$paymentId]
);

// Get status history for timeline.
$statusLog = q_all(
	'SELECT Order_status, Note, Created_at FROM Order_Status_Log WHERE Payment_ID = ? ORDER BY Created_at',
	[$paymentId]
);

// Get latest courier position.
$courier = null;
$courierPosition = null;
if ($tracking['Courier_ID']) {
	$courier = q_one(
		'SELECT Staff_ID, Name, Phone, Plate_number FROM Staff WHERE Staff_ID = ?',
		[(int) $tracking['Courier_ID']]
	);
	$courierPosition = q_one(
		'SELECT Latitude, Longitude, Accuracy, Created_at FROM Delivery_Location
		 WHERE Payment_ID = ? ORDER BY Created_at DESC LIMIT 1',
		[$paymentId]
	);
}

// Calculate estimated arrival.
$estimated = null;
if ($tracking['Estimated_minutes'] && !in_array($tracking['Order_status'], TRACK_STATUS_TERMINAL)) {
	$elapsed = (time() - strtotime($tracking['Created_at'])) / 60;
	$remaining = max(0, (int) $tracking['Estimated_minutes'] - (int) $elapsed);
	$estimated = $remaining;
}

// Fallback: if Dest_Latitude/Dest_Longitude are NULL in Order_Tracking (e.g. older orders
// created before geocoding was available), try to read coordinates from the linked User_Address.
// This is a read-only fallback — it does NOT modify Order_Tracking.
$destLat = $tracking['Dest_Latitude'] !== null ? (float) $tracking['Dest_Latitude'] : null;
$destLng = $tracking['Dest_Longitude'] !== null ? (float) $tracking['Dest_Longitude'] : null;
$destSource = 'tracking'; // source label: 'tracking' | 'address_fallback'

if ($destLat === null && $destLng === null && $tracking['Delivery_Address_ID']) {
	$addrCoords = q_one(
		'SELECT Latitude, Longitude FROM User_Address WHERE Address_ID = ?',
		[(int) $tracking['Delivery_Address_ID']]
	);
	if ($addrCoords) {
		if ($addrCoords['Latitude'] !== null) $destLat = (float) $addrCoords['Latitude'];
		if ($addrCoords['Longitude'] !== null) $destLng = (float) $addrCoords['Longitude'];
		$destSource = 'address_fallback';
	}
}

// Build the response.
json_out(true, [
	'has_tracking'     => true,
	'payment_id'       => $paymentId,
	'payment_status'   => $payment['Payment_status'],
	'order_status'     => $tracking['Order_status'],
	'order_status_label' => track_status_label($tracking['Order_status']),
	'order_status_class' => track_status_class($tracking['Order_status']),
	'tracking_pin'     => $tracking['Tracking_PIN'],
	'estimated_minutes' => $estimated,
	'created_at'       => $tracking['Created_at'],
	'updated_at'       => $tracking['Updated_at'],

	'restaurant' => $restaurant ? [
		'id'        => (int) $restaurant['Restaurant_ID'],
		'name'      => $restaurant['Name'],
		'address'   => $restaurant['Address'],
		'latitude'  => $restaurant['Latitude'] !== null ? (float) $restaurant['Latitude'] : null,
		'longitude' => $restaurant['Longitude'] !== null ? (float) $restaurant['Longitude'] : null,
	] : null,

	'destination' => [
		'latitude'  => $destLat,
		'longitude' => $destLng,
		'source'    => $destSource,
	],

	'courier' => $courier ? [
		'name'         => $courier['Name'],
		'phone'        => $courier['Phone'],
		'plate_number' => $courier['Plate_number'],
		'latitude'     => $courierPosition ? (float) $courierPosition['Latitude'] : ($tracking['Courier_Latitude'] !== null ? (float) $tracking['Courier_Latitude'] : null),
		'longitude'    => $courierPosition ? (float) $courierPosition['Longitude'] : ($tracking['Courier_Longitude'] !== null ? (float) $tracking['Courier_Longitude'] : null),
		'last_update'  => $courierPosition ? $courierPosition['Created_at'] : $tracking['Updated_at'],
	] : null,

	'items' => array_map(fn($it) => [
		'name'       => $it['Item_name'],
		'type'       => $it['Item_type'],
		'unit_price' => (float) $it['Unit_amount'],
		'quantity'   => (int) $it['Quantity'],
		'line_total' => (float) $it['Line_total'],
		'sugar'      => $it['Sugar_level'],
		'ice'        => $it['Ice_level'],
		'remark'     => $it['Remark'],
	], $items),

	'timeline' => array_map(fn($log) => [
		'status'  => $log['Order_status'],
		'label'   => track_status_label($log['Order_status']),
		'icon'    => track_status_icon($log['Order_status']),
		'note'    => $log['Note'],
		'time'    => $log['Created_at'],
	], $statusLog),

	'amount' => (float) $payment['Payment_amount'],
	'subtotal' => (float) $payment['Subtotal_amount'],
	'sst'    => (float) $payment['SST_amount'],
	'discount' => (float) $payment['Discount_amount'],
	'paid_at' => $payment['Paid_at'],
	'provider' => $payment['Provider'],
]);
