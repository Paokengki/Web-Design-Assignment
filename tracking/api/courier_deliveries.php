<?php
/**
 * Courier delivery dashboard — fetches deliveries assigned to the logged-in courier.
 *
 * POST action = list → deliveries assigned to this courier
 * POST action = detail → single delivery detail with full order info
 *
 * Courier only sees deliveries where Courier_ID = their Staff_ID.
 */
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../config.php';

$action = api_start();
$staff = current_staff();

if (!$staff) {
	json_out(false, null, 'Staff sign-in required.', 401);
}

if ($staff['Role'] !== 'Courier') {
	json_out(false, null, 'This dashboard is for Couriers only.', 403);
}

$courierId = (int) $staff['Staff_ID'];

switch ($action) {
	case 'list':
		$orders = q_all(
			"SELECT t.Payment_ID, t.Order_status, t.Created_at, t.Updated_at,
			        t.Dest_Latitude, t.Dest_Longitude,
			        t.Courier_Latitude, t.Courier_Longitude,
			        t.Estimated_minutes, t.Tracking_PIN,
			        p.Payment_amount, p.Restaurant_ID,
			        r.Name AS restaurant_name, r.Address AS restaurant_address,
			        r.Latitude AS rest_lat, r.Longitude AS rest_lng,
			        ua.Recipient, ua.Phone, ua.Line1, ua.Line2,
			        ua.City, ua.Postcode, ua.Latitude AS addr_lat, ua.Longitude AS addr_lng
			 FROM Order_Tracking t
			 INNER JOIN Payment p ON p.Payment_ID = t.Payment_ID
			 LEFT JOIN Restaurant r ON r.Restaurant_ID = t.Restaurant_ID
			 LEFT JOIN User_Address ua ON ua.Address_ID = t.Delivery_Address_ID
			 WHERE t.Courier_ID = ?
			   AND t.Order_status IN ('Assigned', 'Out for Delivery')
			 ORDER BY FIELD(t.Order_status, 'Assigned', 'Out for Delivery'), t.Updated_at ASC",
			[$courierId]
		);

		$result = [];
		foreach ($orders as $o) {
			$items = q_all(
				'SELECT Item_name, Item_type, Quantity, Remark
				 FROM Payment_Item WHERE Payment_ID = ? ORDER BY Payment_Item_ID',
				[(int) $o['Payment_ID']]
			);

			$result[] = [
				'payment_id'     => (int) $o['Payment_ID'],
				'order_status'   => $o['Order_status'],
				'status_label'   => track_status_label($o['Order_status']),
				'status_class'   => track_status_class($o['Order_status']),
				'next_statuses'  => array_map(fn($s) => [
					'status' => $s,
					'label'  => track_status_label($s),
				], track_next_statuses($o['Order_status'])),
				'estimated'      => (int) $o['Estimated_minutes'],
				'tracking_pin'   => $o['Tracking_PIN'],
				'amount'         => (float) $o['Payment_amount'],
				'restaurant'     => [
					'name'      => $o['restaurant_name'] ?? 'Multiple cafés',
					'address'   => $o['restaurant_address'],
					'latitude'  => $o['rest_lat'] !== null ? (float) $o['rest_lat'] : null,
					'longitude' => $o['rest_lng'] !== null ? (float) $o['rest_lng'] : null,
				],
				'customer'       => [
					'name'      => $o['Recipient'],
					'phone'     => $o['Phone'],
					'address'   => trim(($o['Line1'] ?? '') . ($o['Line2'] ? ', ' . $o['Line2'] : '') . ', ' . ($o['Postcode'] ?? '') . ' ' . ($o['City'] ?? '')),
					'latitude'  => $o['addr_lat'] !== null ? (float) $o['addr_lat'] : ($o['Dest_Latitude'] !== null ? (float) $o['Dest_Latitude'] : null),
					'longitude' => $o['addr_lng'] !== null ? (float) $o['addr_lng'] : ($o['Dest_Longitude'] !== null ? (float) $o['Dest_Longitude'] : null),
				],
				'courier_position' => [
					'latitude'  => $o['Courier_Latitude'] !== null ? (float) $o['Courier_Latitude'] : null,
					'longitude' => $o['Courier_Longitude'] !== null ? (float) $o['Courier_Longitude'] : null,
				],
				'items' => array_map(fn($it) => [
					'name'     => $it['Item_name'],
					'type'     => $it['Item_type'],
					'quantity' => (int) $it['Quantity'],
					'remark'   => $it['Remark'],
				], $items),
			];
		}

		json_out(true, ['deliveries' => $result, 'courier_id' => $courierId]);
		break;

	case 'detail':
		$paymentId = (int) input('payment_id', '0');
		if ($paymentId <= 0) json_out(false, null, 'payment_id required.', 422);

		$tracking = q_one('SELECT * FROM Order_Tracking WHERE Payment_ID = ? AND Courier_ID = ?', [$paymentId, $courierId]);
		if (!$tracking) json_out(false, null, 'Delivery not found or not assigned to you.', 404);

		$items = q_all(
			'SELECT Item_name, Item_type, Quantity, Remark
			 FROM Payment_Item WHERE Payment_ID = ? ORDER BY Payment_Item_ID',
			[$paymentId]
		);

		json_out(true, [
			'payment_id'     => $paymentId,
			'order_status'   => $tracking['Order_status'],
			'status_label'   => track_status_label($tracking['Order_status']),
			'next_statuses'  => array_map(fn($s) => [
				'status' => $s,
				'label'  => track_status_label($s),
			], track_next_statuses($tracking['Order_status'])),
			'estimated'      => (int) $tracking['Estimated_minutes'],
			'destination'    => [
				'latitude'  => $tracking['Dest_Latitude'] !== null ? (float) $tracking['Dest_Latitude'] : null,
				'longitude' => $tracking['Dest_Longitude'] !== null ? (float) $tracking['Dest_Longitude'] : null,
			],
			'items' => array_map(fn($it) => [
				'name'     => $it['Item_name'],
				'type'     => $it['Item_type'],
				'quantity' => (int) $it['Quantity'],
				'remark'   => $it['Remark'],
			], $items),
		]);
		break;

	case 'history':
		// Completed deliveries for this courier.
		$orders = q_all(
			"SELECT t.Payment_ID, t.Order_status, t.Created_at, t.Updated_at,
			        p.Payment_amount,
			        r.Name AS restaurant_name,
			        ua.Recipient
			 FROM Order_Tracking t
			 INNER JOIN Payment p ON p.Payment_ID = t.Payment_ID
			 LEFT JOIN Restaurant r ON r.Restaurant_ID = t.Restaurant_ID
			 LEFT JOIN User_Address ua ON ua.Address_ID = t.Delivery_Address_ID
			 WHERE t.Courier_ID = ? AND t.Order_status = 'Delivered'
			 ORDER BY t.Updated_at DESC
			 LIMIT 20",
			[$courierId]
		);

		$result = array_map(fn($o) => [
			'payment_id'    => (int) $o['Payment_ID'],
			'restaurant'    => $o['restaurant_name'] ?? 'Multiple cafés',
			'customer_name' => $o['Recipient'],
			'amount'        => (float) $o['Payment_amount'],
			'delivered_at'  => $o['Updated_at'],
		], $orders);

		json_out(true, ['history' => $result]);
		break;

	default:
		json_out(false, null, 'Unknown action.', 400);
}
