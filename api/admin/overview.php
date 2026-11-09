<?php
/**
 * Control-panel overview (AJAX). Managers + Super Admins.
 * POST action = stats, days (7|14|30)
 */
require __DIR__ . '/../../app/bootstrap.php';

$action = api_start();
require_staff(ADMIN_ROLES);
if ($action !== 'stats') json_out(false, null, 'Unknown action.', 400);

$days = in_array((int) input('days', '14'), [7, 14, 30], true) ? (int) input('days', '14') : 14;
$from = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
$prevFrom = date('Y-m-d 00:00:00', strtotime('-' . (2 * $days - 1) . ' days'));

$ok = "Payment_status = 'SUCCEEDED'";
$now = q_one("SELECT COUNT(*) orders, COALESCE(SUM(Payment_amount), 0) revenue, COUNT(DISTINCT User_ID) buyers FROM Payment WHERE $ok AND Created_at >= ?", [$from]);
$prev = q_one("SELECT COUNT(*) orders, COALESCE(SUM(Payment_amount), 0) revenue FROM Payment WHERE $ok AND Created_at >= ? AND Created_at < ?", [$prevFrom, $from]);

// daily revenue, with empty days filled in
$byDay = [];
foreach (q_all("SELECT DATE(Created_at) d, COUNT(*) orders, SUM(Payment_amount) revenue FROM Payment WHERE $ok AND Created_at >= ? GROUP BY DATE(Created_at)", [$from]) as $r) {
	$byDay[$r['d']] = $r;
}
$series = [];
for ($i = $days - 1; $i >= 0; $i--) {
	$d = date('Y-m-d', strtotime("-$i days"));
	$series[] = ['date' => $d, 'orders' => (int) ($byDay[$d]['orders'] ?? 0), 'revenue' => round((float) ($byDay[$d]['revenue'] ?? 0), 2)];
}

// café ranking by orders (all time), ties broken by revenue — same rule as the old ranking page
$ranking = q_all("SELECT r.Restaurant_ID, r.Name, COUNT(p.Payment_ID) orders, COALESCE(SUM(p.Payment_amount), 0) revenue
	FROM Restaurant r LEFT JOIN Payment p ON p.Restaurant_ID = r.Restaurant_ID AND p.$ok
	GROUP BY r.Restaurant_ID, r.Name ORDER BY orders DESC, revenue DESC, r.Name LIMIT 10");

$top = q_all("SELECT pi.Item_name, SUM(pi.Quantity) qty FROM Payment_Item pi JOIN Payment p ON p.Payment_ID = pi.Payment_ID
	WHERE p.$ok GROUP BY pi.Item_name ORDER BY qty DESC LIMIT 5");

json_out(true, [
	'days' => $days,
	'kpi' => [
		'revenue' => round((float) $now['revenue'], 2), 'revenue_prev' => round((float) $prev['revenue'], 2),
		'orders' => (int) $now['orders'], 'orders_prev' => (int) $prev['orders'],
		'buyers' => (int) $now['buyers'],
		'members' => (int) q_one('SELECT COUNT(*) c FROM User WHERE Deleted_at IS NULL')['c'],
		'active_promos' => (int) q_one('SELECT COUNT(*) c FROM Promotion WHERE Is_active = 1 AND Starts_at <= NOW() AND Expires_at > NOW() AND (Max_redemptions IS NULL OR Redemption_count < Max_redemptions)')['c'],
		'unread' => (int) q_one('SELECT COUNT(*) c FROM contact_us WHERE Is_read = 0')['c'],
	],
	'series' => $series,
	'ranking' => array_map(fn($r) => ['name' => $r['Name'], 'orders' => (int) $r['orders'], 'revenue' => round((float) $r['revenue'], 2)], $ranking),
	'top_items' => array_map(fn($r) => ['name' => $r['Item_name'], 'qty' => (int) $r['qty']], $top),
]);
