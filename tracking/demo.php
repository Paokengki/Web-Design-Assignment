<?php
/**
 * Demo order lifecycle + driver pools.
 *
 * Every time a tracking page asks for an order's status, track_demo_tick() checks how long the
 * order has been in its current status and moves it on (10 s Pending → 5 s each after that):
 *
 *   Pending ─10s→ Accepted ─5s→ Preparing ─5s→ Ready for Pickup ─5s→ Assigned ─5s→ Out for Delivery ─5s→ Delivered
 *
 * Timing is server-side (Order_Tracking.Status_changed_at), so it stays correct even if the
 * page is closed and reopened: several steps are caught up at once, each stamped at the time
 * it "really" happened.
 *
 * Driver pools: every café has its own couriers (Staff.Role = 'Courier', Assigned_Restaurant_ID).
 * At "Assigned" a free courier of the order's café is picked; they are busy while the order is
 * Assigned / Out for Delivery and free again once it is Delivered or Cancelled.
 *
 * Auto-messages (app/services/chat.php): restaurant at Accepted, driver at Assigned and Delivered.
 */
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../app/services/chat.php';
require_once __DIR__ . '/../app/services/geocode.php';

/** The café whose kitchen and driver pool handle an order (multi-café: the first café). */
function track_order_cafe(int $paymentId): ?array
{
	$id = chat_order_cafe($paymentId);
	return $id ? q_one('SELECT Restaurant_ID, Name, Latitude, Longitude FROM Restaurant WHERE Restaurant_ID = ?', [$id]) : null;
}

/**
 * Make sure the order has a delivery point on the map:
 * the address's coordinates → otherwise look the address up once (OpenStreetMap)
 * → otherwise a point about 1.5 km from the café, so the demo always has a route.
 */
function track_demo_destination(array $t): array
{
	if ($t['Dest_Latitude'] !== null && $t['Dest_Longitude'] !== null) return [(float) $t['Dest_Latitude'], (float) $t['Dest_Longitude']];
	$lat = $lng = null;
	if ($t['Delivery_Address_ID']) {
		$a = q_one('SELECT * FROM User_Address WHERE Address_ID = ?', [(int) $t['Delivery_Address_ID']]);
		if ($a && $a['Latitude'] !== null) {
			[$lat, $lng] = [(float) $a['Latitude'], (float) $a['Longitude']];
		} elseif ($a) {
			$hit = geocode_address((string) $a['Line1'], (string) $a['City'], (string) $a['Postcode'], (string) $a['State']);
			// only trust a hit within ~25 km of the café (bad addresses can geocode to another state)
			$cafe = track_order_cafe((int) $t['Payment_ID']);
			if ($hit && (!$cafe || $cafe['Latitude'] === null || (abs($hit['lat'] - $cafe['Latitude']) < 0.25 && abs($hit['lng'] - $cafe['Longitude']) < 0.25))) {
				[$lat, $lng] = [$hit['lat'], $hit['lng']];
				q('UPDATE User_Address SET Latitude = ?, Longitude = ? WHERE Address_ID = ? AND Latitude IS NULL', [$lat, $lng, (int) $a['Address_ID']]);
			}
		}
	}
	if ($lat === null) {
		$cafe = track_order_cafe((int) $t['Payment_ID']);
		$base = ($cafe && $cafe['Latitude'] !== null) ? [(float) $cafe['Latitude'], (float) $cafe['Longitude']] : [TRACK_MAP_DEFAULT_LAT, TRACK_MAP_DEFAULT_LNG];
		$k = ((int) $t['Payment_ID'] % 8) * M_PI / 4; // spread demo destinations around the café
		[$lat, $lng] = [$base[0] + 0.011 * sin($k), $base[1] + 0.011 * cos($k)];
	}
	q('UPDATE Order_Tracking SET Dest_Latitude = ?, Dest_Longitude = ? WHERE Tracking_ID = ?', [round($lat, 7), round($lng, 7), (int) $t['Tracking_ID']]);
	return [$lat, $lng];
}

/**
 * Pick a free courier from the café's pool and lock them in.
 * Each candidate row is locked (FOR UPDATE) and re-checked, so two orders can never
 * grab the same driver at the same moment. Returns the Staff_ID or null if all are busy.
 */
function track_pick_driver(int $cafeId): ?int
{
	$busy = "'" . implode("','", TRACK_DRIVER_BUSY_STATUSES) . "'";
	$candidates = q_all("SELECT s.Staff_ID FROM Staff s
		WHERE s.Role = 'Courier' AND s.Status = 'Active' AND s.Assigned_Restaurant_ID = ?
		  AND NOT EXISTS (SELECT 1 FROM Order_Tracking t WHERE t.Courier_ID = s.Staff_ID AND t.Order_status IN ($busy))
		ORDER BY RAND() LIMIT 10", [$cafeId]);
	foreach ($candidates as $c) {
		$id = (int) $c['Staff_ID'];
		q('SELECT Staff_ID FROM Staff WHERE Staff_ID = ? FOR UPDATE', [$id]);
		$stillFree = !q_one("SELECT 1 FROM Order_Tracking WHERE Courier_ID = ? AND Order_status IN ($busy) LOCK IN SHARE MODE", [$id]);
		if ($stillFree) return $id;
	}
	return null;
}

/** Driver pool numbers for a café: total / busy / free. */
function track_driver_pool(int $cafeId): array
{
	$busy = "'" . implode("','", TRACK_DRIVER_BUSY_STATUSES) . "'";
	$r = q_one("SELECT COUNT(*) total,
			SUM(EXISTS (SELECT 1 FROM Order_Tracking t WHERE t.Courier_ID = s.Staff_ID AND t.Order_status IN ($busy))) busy
		FROM Staff s WHERE s.Role = 'Courier' AND s.Status = 'Active' AND s.Assigned_Restaurant_ID = ?", [$cafeId]);
	$total = (int) $r['total'];
	$busyN = (int) $r['busy'];
	return ['total' => $total, 'busy' => $busyN, 'free' => $total - $busyN];
}

/**
 * Move an order into a new status at time $at (unix seconds): parent row, café rows, log, extras.
 * Must run inside the caller's transaction.
 */
function track_apply_status(array $t, string $status, int $at, ?string $note = null, ?int $driverId = null): void
{
	$when = date('Y-m-d H:i:s', $at);
	$pid = (int) $t['Payment_ID'];

	if ($status === TRACK_STATUS_ASSIGNED) {
		q('UPDATE Order_Tracking SET Order_status = ?, Status_changed_at = ?, Courier_ID = ?, Assigned_By = NULL, Updated_at = NOW() WHERE Tracking_ID = ?',
			[$status, $when, $driverId, (int) $t['Tracking_ID']]);
	} else {
		q('UPDATE Order_Tracking SET Order_status = ?, Status_changed_at = ?, Updated_at = NOW() WHERE Tracking_ID = ?', [$status, $when, (int) $t['Tracking_ID']]);
	}

	// keep the per-café rows (kitchen view) in step with the overall status
	$item = [
		TRACK_STATUS_ACCEPTED => TRACK_ITEM_ACCEPTED, TRACK_STATUS_PREPARING => TRACK_ITEM_PREPARING,
		TRACK_STATUS_READY => TRACK_ITEM_READY, TRACK_STATUS_DELIVERED => TRACK_ITEM_COMPLETED,
	][$status] ?? null;
	if ($item) q('UPDATE Order_Tracking_Item SET Item_status = ?, Updated_at = ? WHERE Payment_ID = ?', [$item, $when, $pid]);

	q('INSERT INTO Order_Status_Log (Payment_ID, Order_status, Log_Level, Changed_By, Note, Created_at) VALUES (?, ?, ?, NULL, ?, ?)',
		[$pid, $status, 'parent', $note ?? 'Updated automatically (demo)', $when]);
}

/** Courier position during "Out for Delivery": a straight line café → customer by elapsed time. */
function track_demo_move_driver(array $t, array $cafe, array $dest, int $now): void
{
	$secs = DEMO_STATUS_SECONDS[TRACK_STATUS_OUT_FOR_DELIVERY];
	$p = max(0, min(1, ($now - strtotime($t['Status_changed_at'])) / $secs));
	$lat = round($cafe[0] + ($dest[0] - $cafe[0]) * $p, 7);
	$lng = round($cafe[1] + ($dest[1] - $cafe[1]) * $p, 7);
	if ((float) $t['Courier_Latitude'] === $lat && (float) $t['Courier_Longitude'] === $lng) return;
	q('UPDATE Order_Tracking SET Courier_Latitude = ?, Courier_Longitude = ? WHERE Tracking_ID = ?', [$lat, $lng, (int) $t['Tracking_ID']]);
	if ($t['Courier_ID']) q('INSERT INTO Delivery_Location (Payment_ID, Courier_ID, Latitude, Longitude, Accuracy) VALUES (?, ?, ?, ?, 5)', [(int) $t['Payment_ID'], (int) $t['Courier_ID'], $lat, $lng]);
}

/**
 * Advance one order on the demo timer (no-op when DEMO_MODE is off or the order is finished).
 * Safe to call from many pages at once: the order row is locked while it is advanced.
 */
function track_demo_tick(int $paymentId): void
{
	if (!DEMO_MODE) return;
	$peek = q_one('SELECT Order_status FROM Order_Tracking WHERE Payment_ID = ?', [$paymentId]);
	if (!$peek || in_array($peek['Order_status'], TRACK_STATUS_TERMINAL, true)) return;

	$db = db();
	$db->begin_transaction();
	try {
		$t = q_one('SELECT * FROM Order_Tracking WHERE Payment_ID = ? FOR UPDATE', [$paymentId]);
		if (!$t || in_array($t['Order_status'], TRACK_STATUS_TERMINAL, true)) {
			$db->commit();
			return;
		}
		$cafeRow = track_order_cafe($paymentId);
		$cafe = ($cafeRow && $cafeRow['Latitude'] !== null) ? [(float) $cafeRow['Latitude'], (float) $cafeRow['Longitude']] : [TRACK_MAP_DEFAULT_LAT, TRACK_MAP_DEFAULT_LNG];
		$dest = track_demo_destination($t);
		$now = time();
		$since = strtotime($t['Status_changed_at'] ?? $t['Created_at']);

		// catch up every step whose time has passed
		for ($guard = 0; $guard < 8; $guard++) {
			$status = $t['Order_status'];
			$wait = DEMO_STATUS_SECONDS[$status] ?? null;
			if ($wait === null || $now - $since < $wait) break;

			$idx = array_search($status, TRACK_STATUS_FLOW, true);
			$next = TRACK_STATUS_FLOW[$idx + 1] ?? null;
			if (!$next) break;
			$at = $since + $wait;

			if ($next === TRACK_STATUS_ASSIGNED) {
				$driver = $cafeRow ? track_pick_driver((int) $cafeRow['Restaurant_ID']) : null;
				if (!$driver) break; // every driver of this café is out: wait at Ready for Pickup
				$name = q_one('SELECT Name FROM Staff WHERE Staff_ID = ?', [$driver])['Name'];
				track_apply_status($t, $next, $at, 'Driver ' . $name . ' assigned', $driver);
				q('UPDATE Order_Tracking SET Courier_Latitude = ?, Courier_Longitude = ? WHERE Tracking_ID = ?', [$cafe[0], $cafe[1], (int) $t['Tracking_ID']]);
				$t['Courier_ID'] = $driver;
				chat_auto($paymentId, 'driver', "Hi! I'm {$name}, your driver from " . ($cafeRow['Name'] ?? 'the café') . ". I'll pick up your order and head your way shortly.");
			} elseif ($next === TRACK_STATUS_DELIVERED) {
				track_apply_status($t, $next, $at, 'Delivered by your driver');
				q('UPDATE Order_Tracking SET Courier_Latitude = ?, Courier_Longitude = ? WHERE Tracking_ID = ?', [$dest[0], $dest[1], (int) $t['Tracking_ID']]);
				chat_auto($paymentId, 'driver', "Your food has arrived! Enjoy your meal. Don't forget to tap Complete Order to collect your points.");
			} else {
				track_apply_status($t, $next, $at);
				if ($next === TRACK_STATUS_ACCEPTED) {
					chat_auto($paymentId, 'restaurant', 'Thanks for your order! ' . ($cafeRow['Name'] ?? 'We') . ' has accepted it and the kitchen is getting started.');
				}
			}
			$t['Order_status'] = $next;
			$t['Status_changed_at'] = date('Y-m-d H:i:s', $at);
			$since = $at;
		}

		if ($t['Order_status'] === TRACK_STATUS_OUT_FOR_DELIVERY) {
			$t = q_one('SELECT * FROM Order_Tracking WHERE Tracking_ID = ?', [(int) $t['Tracking_ID']]);
			track_demo_move_driver($t, $cafe, $dest, $now);
		}
		$db->commit();
	} catch (Throwable $e) {
		$db->rollback();
		error_log('CafeDash demo tick #' . $paymentId . ': ' . $e->getMessage());
	}
}

/** Advance every unfinished order (used by the kitchen / manager / courier pages). */
function track_demo_tick_all(): void
{
	if (!DEMO_MODE) return;
	foreach (q_all("SELECT Payment_ID FROM Order_Tracking WHERE Order_status NOT IN ('Delivered', 'Cancelled')") as $r) {
		track_demo_tick((int) $r['Payment_ID']);
	}
}

/**
 * Where the demo is up to, for the tracking page:
 * seconds spent in the current status, its length, and (while out for delivery) progress 0–1.
 */
function track_demo_info(array $t): array
{
	$status = $t['Order_status'];
	$len = DEMO_STATUS_SECONDS[$status] ?? null;
	$elapsed = max(0, time() - strtotime($t['Status_changed_at'] ?? $t['Created_at']));
	return [
		'enabled'  => DEMO_MODE,
		'status'   => $status,
		'elapsed'  => $elapsed,
		'length'   => $len,
		'remaining' => $len !== null ? max(0, $len - $elapsed) : null,
		'progress' => $status === TRACK_STATUS_OUT_FOR_DELIVERY && $len ? min(1, $elapsed / $len) : ($status === TRACK_STATUS_DELIVERED ? 1 : 0),
	];
}
