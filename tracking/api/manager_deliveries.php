<?php
/**
 * Manager delivery dashboard — fetches orders ready for courier assignment
 * and lists available couriers.
 *
 * POST action = ready_orders → orders with status 'Ready for Pickup'
 * POST action = active_deliveries → orders with courier assigned (Assigned / Out for Delivery)
 * POST action = available_couriers → active Couriers not currently on a delivery
 * POST action = assign_courier → alias for update_status with new_status=Assigned
 *
 * Manager / Super Admin only.
 */
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../config.php';

$action = api_start();
$staff = current_staff();

if (!$staff) {
	json_out(false, null, 'Staff sign-in required.', 401);
}

if (!in_array($staff['Role'], ADMIN_ROLES, true)) {
	json_out(false, null, 'Manager access required.', 403);
}

switch ($action) {
	case 'ready_orders':
		$orders = q_all(
			"SELECT t.Payment_ID, t.Order_status, t.Created_at, t.Tracking_PIN,
			        t.Estimated_minutes,
			        p.Payment_amount, p.Restaurant_ID,
			        r.Name AS restaurant_name,
			        ua.Recipient, ua.Phone, ua.Line1, ua.Line2, ua.City, ua.Postcode
			 FROM Order_Tracking t
			 INNER JOIN Payment p ON p.Payment_ID = t.Payment_ID
			 LEFT JOIN Restaurant r ON r.Restaurant_ID = t.Restaurant_ID
			 LEFT JOIN User_Address ua ON ua.Address_ID = t.Delivery_Address_ID
			 WHERE t.Order_status = 'Ready for Pickup'
			 ORDER BY t.Updated_at ASC"
		);

		$result = [];
		foreach ($orders as $o) {
			// Count items for this order.
			$itemCount = q_one('SELECT COUNT(*) c FROM Payment_Item WHERE Payment_ID = ?', [(int) $o['Payment_ID']]);

			$result[] = [
				'payment_id'     => (int) $o['Payment_ID'],
				'order_status'   => $o['Order_status'],
				'status_label'   => track_status_label($o['Order_status']),
				'restaurant'     => $o['restaurant_name'] ?? 'Multiple cafés',
				'customer_name'  => $o['Recipient'],
				'customer_phone' => $o['Phone'],
				'address'        => trim(($o['Line1'] ?? '') . ($o['Line2'] ? ', ' . $o['Line2'] : '') . ', ' . ($o['Postcode'] ?? '') . ' ' . ($o['City'] ?? '')),
				'item_count'     => (int) $itemCount['c'],
				'amount'         => (float) $o['Payment_amount'],
				'estimated'      => (int) $o['Estimated_minutes'],
				'ready_since'    => $o['Updated_at'] ?? $o['Created_at'],
			];
		}

		json_out(true, ['orders' => $result]);
		break;

	case 'active_deliveries':
		$orders = q_all(
			"SELECT t.Payment_ID, t.Order_status, t.Courier_ID, t.Created_at,
			        t.Courier_Latitude, t.Courier_Longitude,
			        t.Estimated_minutes, t.Tracking_PIN,
			        p.Payment_amount, p.Restaurant_ID,
			        r.Name AS restaurant_name,
			        ua.Recipient, ua.Phone, ua.Line1, ua.Line2, ua.City, ua.Postcode,
			        s.Name AS courier_name, s.Phone AS courier_phone, s.Plate_number
			 FROM Order_Tracking t
			 INNER JOIN Payment p ON p.Payment_ID = t.Payment_ID
			 LEFT JOIN Restaurant r ON r.Restaurant_ID = t.Restaurant_ID
			 LEFT JOIN User_Address ua ON ua.Address_ID = t.Delivery_Address_ID
			 LEFT JOIN Staff s ON s.Staff_ID = t.Courier_ID
			 WHERE t.Order_status IN ('Assigned', 'Out for Delivery')
			 ORDER BY t.Updated_at DESC"
		);

		$result = [];
		foreach ($orders as $o) {
			$result[] = [
				'payment_id'       => (int) $o['Payment_ID'],
				'order_status'     => $o['Order_status'],
				'status_label'     => track_status_label($o['Order_status']),
				'restaurant'       => $o['restaurant_name'] ?? 'Multiple cafés',
				'customer_name'    => $o['Recipient'],
				'customer_phone'   => $o['Phone'],
				'address'          => trim(($o['Line1'] ?? '') . ($o['Line2'] ? ', ' . $o['Line2'] : '') . ', ' . ($o['Postcode'] ?? '') . ' ' . ($o['City'] ?? '')),
				'courier'          => [
					'id'           => (int) $o['Courier_ID'],
					'name'         => $o['courier_name'],
					'phone'        => $o['courier_phone'],
					'plate_number' => $o['Plate_number'],
				],
				'courier_position' => [
					'latitude'  => $o['Courier_Latitude'] !== null ? (float) $o['Courier_Latitude'] : null,
					'longitude' => $o['Courier_Longitude'] !== null ? (float) $o['Courier_Longitude'] : null,
				],
				'amount'           => (float) $o['Payment_amount'],
				'estimated'        => (int) $o['Estimated_minutes'],
				'updated_at'       => $o['Updated_at'] ?? $o['Created_at'],
			];
		}

		json_out(true, ['deliveries' => $result]);
		break;

	case 'available_couriers':
		// Couriers who are Active and not currently assigned to any active delivery.
		$couriers = q_all(
			"SELECT s.Staff_ID, s.Name, s.Phone, s.Plate_number
			 FROM Staff s
			 WHERE s.Role = 'Courier' AND s.Status = 'Active'
			   AND s.Staff_ID NOT IN (
			       SELECT Courier_ID FROM Order_Tracking
			       WHERE Order_status IN ('Assigned', 'Out for Delivery')
			       AND Courier_ID IS NOT NULL
			   )
			 ORDER BY s.Name"
		);

		$result = array_map(fn($c) => [
			'id'           => (int) $c['Staff_ID'],
			'name'         => $c['Name'],
			'phone'        => $c['Phone'],
			'plate_number' => $c['Plate_number'],
		], $couriers);

		json_out(true, ['couriers' => $result]);
		break;

	case 'assigned_couriers':
		// Couriers currently on active delivery (for showing who's busy).
		$couriers = q_all(
			"SELECT s.Staff_ID, s.Name, s.Phone, s.Plate_number,
			        COUNT(t.Payment_ID) AS active_count
			 FROM Staff s
			 INNER JOIN Order_Tracking t ON t.Courier_ID = s.Staff_ID
			 WHERE s.Role = 'Courier' AND s.Status = 'Active'
			   AND t.Order_status IN ('Assigned', 'Out for Delivery')
			 GROUP BY s.Staff_ID
			 ORDER BY s.Name"
		);

		$result = array_map(fn($c) => [
			'id'           => (int) $c['Staff_ID'],
			'name'         => $c['Name'],
			'phone'        => $c['Phone'],
			'plate_number' => $c['Plate_number'],
			'active_count' => (int) $c['active_count'],
		], $couriers);

		json_out(true, ['couriers' => $result]);
		break;

	default:
		json_out(false, null, 'Unknown action.', 400);
}
