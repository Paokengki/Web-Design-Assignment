<?php
/**
 * Kitchen order queue — fetches orders for a Kitchen Staff member's assigned restaurant.
 *
 * POST action = list → returns active orders with items filtered to the kitchen's restaurant.
 * POST action = detail → returns full detail for one order (items filtered to their restaurant).
 *
 * Kitchen Staff only see orders that:
 *   - Have Payment_status = 'SUCCEEDED'
 *   - Have an Order_Tracking row
 *   - Are NOT in a terminal status (Delivered/Cancelled)
 *   - Contain items from their assigned restaurant
 *
 * Multi-café orders: each café's kitchen only sees their own items,
 * but the order appears in their queue.
 */
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../config.php';

$action = api_start();
$staff = current_staff();

if (!$staff) {
	json_out(false, null, 'Staff sign-in required.', 401);
}

if ($staff['Role'] !== 'Kitchen Staff') {
	json_out(false, null, 'This dashboard is for Kitchen Staff only.', 403);
}

$assignedRest = (int) ($staff['Assigned_Restaurant_ID'] ?? 0);
if ($assignedRest <= 0) {
	json_out(false, null, 'You are not assigned to a café. Contact your manager.', 403);
}

switch ($action) {
	case 'list':
		// Get active tracking orders for this kitchen's restaurant.
		// We check both Restaurant_ID match (single-café orders) and
		// Payment_Item → Food → Restaurant_ID match (multi-café orders).
		$orders = q_all(
			"SELECT t.Payment_ID, t.Order_status, t.Created_at, t.Updated_at,
			        t.Tracking_PIN, t.Estimated_minutes,
			        p.Payment_amount, p.Created_at AS paid_at,
			        p.Restaurant_ID AS payment_restaurant,
			        ua.Recipient, ua.Phone, ua.Line1, ua.Line2, ua.City, ua.Postcode
			 FROM Order_Tracking t
			 INNER JOIN Payment p ON p.Payment_ID = t.Payment_ID
			 LEFT JOIN User_Address ua ON ua.Address_ID = t.Delivery_Address_ID
			 WHERE t.Order_status NOT IN ('Delivered', 'Cancelled')
			   AND p.Payment_status = 'SUCCEEDED'
			   AND (
			       t.Restaurant_ID = ?
			       OR EXISTS (
			           SELECT 1 FROM Payment_Item pi
			           JOIN Food f ON f.Food_ID = pi.Food_ID
			           WHERE pi.Payment_ID = t.Payment_ID AND f.Restaurant_ID = ?
			       )
			   )
			 ORDER BY
			   FIELD(t.Order_status, 'Pending', 'Accepted', 'Preparing', 'Ready for Pickup'),
			   t.Created_at ASC",
			[$assignedRest, $assignedRest]
		);

		// For each order, fetch only the items belonging to this restaurant.
		$result = [];
		foreach ($orders as $o) {
			$items = q_all(
				'SELECT pi.Item_name, pi.Item_type, pi.Unit_amount, pi.Quantity,
				        pi.Line_total, pi.Sugar_level, pi.Ice_level, pi.Remark
				 FROM Payment_Item pi
				 LEFT JOIN Food f ON f.Food_ID = pi.Food_ID
				 WHERE pi.Payment_ID = ? AND (f.Restaurant_ID = ? OR pi.Food_ID IS NULL)
				 ORDER BY pi.Payment_Item_ID',
				[(int) $o['Payment_ID'], $assignedRest]
			);

			$result[] = [
				'payment_id'   => (int) $o['Payment_ID'],
				'order_status' => $o['Order_status'],
				'status_label' => track_status_label($o['Order_status']),
				'status_class' => track_status_class($o['Order_status']),
				'next_statuses' => array_map(fn($s) => [
					'status' => $s,
					'label'  => track_status_label($s),
				], track_next_statuses($o['Order_status'])),
				'created_at'   => $o['Created_at'],
				'paid_at'      => $o['paid_at'],
				'estimated'    => (int) $o['Estimated_minutes'],
				'tracking_pin' => $o['Tracking_PIN'],
				'amount'       => (float) $o['Payment_amount'],
				'customer'     => [
					'name'    => $o['Recipient'],
					'phone'   => $o['Phone'],
					'address' => trim(($o['Line1'] ?? '') . ($o['Line2'] ? ', ' . $o['Line2'] : '') . ', ' . ($o['Postcode'] ?? '') . ' ' . ($o['City'] ?? '')),
				],
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
			];
		}

		json_out(true, ['orders' => $result, 'restaurant_id' => $assignedRest]);
		break;

	case 'detail':
		$paymentId = (int) input('payment_id', '0');
		if ($paymentId <= 0) json_out(false, null, 'payment_id required.', 422);

		$tracking = q_one('SELECT * FROM Order_Tracking WHERE Payment_ID = ?', [$paymentId]);
		if (!$tracking) json_out(false, null, 'Order not found.', 404);

		// Verify this restaurant has items in the order.
		$hasItems = q_one(
			'SELECT 1 FROM Payment_Item pi JOIN Food f ON f.Food_ID = pi.Food_ID
			 WHERE pi.Payment_ID = ? AND f.Restaurant_ID = ? LIMIT 1',
			[$paymentId, $assignedRest]
		);
		if (!$hasItems && (int) $tracking['Restaurant_ID'] !== $assignedRest) {
			json_out(false, null, 'This order does not belong to your café.', 403);
		}

		$items = q_all(
			'SELECT pi.Item_name, pi.Item_type, pi.Unit_amount, pi.Quantity,
			        pi.Line_total, pi.Sugar_level, pi.Ice_level, pi.Remark
			 FROM Payment_Item pi
			 LEFT JOIN Food f ON f.Food_ID = pi.Food_ID
			 WHERE pi.Payment_ID = ? AND (f.Restaurant_ID = ? OR pi.Food_ID IS NULL)
			 ORDER BY pi.Payment_Item_ID',
			[$paymentId, $assignedRest]
		);

		json_out(true, [
			'payment_id'   => $paymentId,
			'order_status' => $tracking['Order_status'],
			'status_label' => track_status_label($tracking['Order_status']),
			'next_statuses' => array_map(fn($s) => [
				'status' => $s,
				'label'  => track_status_label($s),
			], track_next_statuses($tracking['Order_status'])),
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
		]);
		break;

	default:
		json_out(false, null, 'Unknown action.', 400);
}
