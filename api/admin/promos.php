<?php
/**
 * Module 2 — Promotional vouchers (AJAX). Managers and Super Admins.
 * POST action = list | create | update | toggle | delete
 */
require __DIR__ . '/../../app/bootstrap.php';

$action = api_start();
$staff = require_staff(ADMIN_ROLES);

const PROMO_SELECT = "SELECT p.*, s.Name AS Created_by_name,
	CASE
		WHEN p.Expires_at <= NOW() OR (p.Max_redemptions IS NOT NULL AND p.Redemption_count >= p.Max_redemptions) THEN 'expired'
		WHEN p.Starts_at > NOW() THEN 'scheduled'
		ELSE 'active'
	END AS status
	FROM Promotion p LEFT JOIN Staff s ON s.Staff_ID = p.Created_by";

function promo_by_id(int $id): array
{
	$row = q_one(PROMO_SELECT . ' WHERE p.Promo_ID = ?', [$id]);
	if (!$row) json_out(false, null, 'Promotion not found.', 404);
	return $row;
}

function promo_input(?int $ignoreId = null): array
{
	$d = [
		'Code'             => strtoupper(input('Code')),
		'Title'            => input('Title'),
		'Description'      => input('Description'),
		'Discount_percent' => input('Discount_percent'),
		'Max_redemptions'  => input('Max_redemptions'),
		'Min_spend'        => input('Min_spend', '0'),
		'Starts_at'        => input('Starts_at'),
		'Expires_at'       => input('Expires_at'),
	];
	$e = [];
	if (!preg_match('/^[A-Z0-9-]{4,20}$/', $d['Code'])) $e['Code'] = '4–20 characters: A–Z, 0–9 or dash.';
	if ($d['Title'] === '' || mb_strlen($d['Title']) > 120) $e['Title'] = 'Give the campaign a short title.';
	if (mb_strlen($d['Description']) > 255) $e['Description'] = 'Keep it under 255 characters.';
	if (!is_numeric($d['Discount_percent']) || $d['Discount_percent'] <= 0 || $d['Discount_percent'] > 100) $e['Discount_percent'] = 'Between 1 and 100.';
	if ($d['Max_redemptions'] !== '' && (!ctype_digit($d['Max_redemptions']) || (int) $d['Max_redemptions'] < 1)) $e['Max_redemptions'] = 'Whole number, or leave empty for unlimited.';
	if ($d['Min_spend'] === '') $d['Min_spend'] = '0';
	if (!is_numeric($d['Min_spend']) || $d['Min_spend'] < 0) $e['Min_spend'] = 'Zero or more.';

	$start = DateTime::createFromFormat('Y-m-d\TH:i', $d['Starts_at']);
	$end = DateTime::createFromFormat('Y-m-d\TH:i', $d['Expires_at']);
	if (!$start) $e['Starts_at'] = 'Pick a start date and time.';
	if (!$end) $e['Expires_at'] = 'Pick an expiry date and time.';
	if ($start && $end && $end <= $start) $e['Expires_at'] = 'Must be after the start.';

	if (!isset($e['Code'])) {
		$clash = q_one('SELECT Promo_ID FROM Promotion WHERE Code = ? AND Promo_ID <> ?', [$d['Code'], $ignoreId ?? 0]);
		if ($clash) $e['Code'] = 'This code already exists.';
	}
	if ($e) json_out(false, ['fields' => $e], 'Please check the highlighted fields.', 422);

	$d['Discount_percent'] = round((float) $d['Discount_percent'], 2);
	$d['Min_spend'] = round((float) $d['Min_spend'], 2);
	$d['Max_redemptions'] = $d['Max_redemptions'] === '' ? null : (int) $d['Max_redemptions'];
	$d['Description'] = $d['Description'] === '' ? null : $d['Description'];
	$d['Starts_at'] = $start->format('Y-m-d H:i:00');
	$d['Expires_at'] = $end->format('Y-m-d H:i:00');
	return $d;
}

switch ($action) {
	case 'list':
		$rows = q_all(PROMO_SELECT . ' ORDER BY p.Starts_at DESC');
		$stats = ['active' => 0, 'scheduled' => 0, 'expired' => 0, 'redemptions' => 0, 'paused' => 0];
		foreach ($rows as $r) {
			$stats[$r['status']]++;
			$stats['redemptions'] += (int) $r['Redemption_count'];
			if (!(int) $r['Is_active']) $stats['paused']++;
		}
		json_out(true, ['promos' => $rows, 'stats' => $stats, 'now' => date('c')]);

	case 'create':
		$d = promo_input();
		q('INSERT INTO Promotion (Code, Title, Description, Discount_percent, Max_redemptions, Min_spend, Starts_at, Expires_at, Created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
			$d['Code'], $d['Title'], $d['Description'], $d['Discount_percent'], $d['Max_redemptions'], $d['Min_spend'], $d['Starts_at'], $d['Expires_at'], (int) $staff['Staff_ID'],
		]);
		json_out(true, promo_by_id((int) db()->insert_id));

	case 'update':
		$id = (int) input('Promo_ID');
		$existing = promo_by_id($id);
		$d = promo_input($id);
		if ($d['Max_redemptions'] !== null && $d['Max_redemptions'] < (int) $existing['Redemption_count']) {
			json_out(false, ['fields' => ['Max_redemptions' => 'Already redeemed ' . $existing['Redemption_count'] . ' times.']], 'Limit is below current redemptions.', 422);
		}
		q('UPDATE Promotion SET Code = ?, Title = ?, Description = ?, Discount_percent = ?, Max_redemptions = ?, Min_spend = ?, Starts_at = ?, Expires_at = ? WHERE Promo_ID = ?', [
			$d['Code'], $d['Title'], $d['Description'], $d['Discount_percent'], $d['Max_redemptions'], $d['Min_spend'], $d['Starts_at'], $d['Expires_at'], $id,
		]);
		json_out(true, promo_by_id($id));

	case 'toggle':
		// Instantly pause / resume a code without changing its dates.
		$id = (int) input('Promo_ID');
		promo_by_id($id);
		q('UPDATE Promotion SET Is_active = 1 - Is_active WHERE Promo_ID = ?', [$id]);
		json_out(true, promo_by_id($id));

	case 'delete':
		$id = (int) input('Promo_ID');
		promo_by_id($id);
		q('DELETE FROM Promotion WHERE Promo_ID = ?', [$id]);
		json_out(true, ['Promo_ID' => $id]);

	default:
		json_out(false, null, 'Unknown action.', 400);
}
