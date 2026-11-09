<?php
/**
 * Module 3 — Staff management (AJAX). Super Admin only.
 * POST action = list | create | update | set_role | set_status | reset_password
 *
 * Guards: you cannot change your own role/status, and Super Admin accounts can only be
 * edited from the database (so nobody can lock every admin out from the UI).
 */
require __DIR__ . '/../../app/bootstrap.php';

$action = api_start();
$me = require_staff(['Super Admin']);

const STAFF_COLUMNS = 'Staff_ID, Name, Email, Phone, Role, Status, Plate_number, Last_login_at, Created_at, Updated_at';
const STAFF_STATUSES = ['Active', 'Suspended', 'Deactivated'];
const PER_PAGE = 8;

function staff_row(int $id): array
{
	$row = q_one('SELECT ' . STAFF_COLUMNS . ' FROM Staff WHERE Staff_ID = ?', [$id]);
	if (!$row) json_out(false, null, 'Staff member not found.', 404);
	return $row;
}

/** Load the target and refuse changes to yourself or to Super Admins. */
function editable_target(array $me): array
{
	$t = staff_row((int) input('Staff_ID'));
	if ((int) $t['Staff_ID'] === (int) $me['Staff_ID']) json_out(false, null, 'You cannot change your own account here.', 403);
	if ($t['Role'] === 'Super Admin') json_out(false, null, 'Super Admin accounts are protected.', 403);
	return $t;
}

function staff_input(?int $ignoreId = null): array
{
	$d = [
		'Name'         => input('Name'),
		'Email'        => strtolower(input('Email')),
		'Phone'        => input('Phone'),
		'Plate_number' => strtoupper(input('Plate_number')),
	];
	$e = [];
	if ($d['Name'] === '' || mb_strlen($d['Name']) > 255) $e['Name'] = 'Enter a name.';
	if (!filter_var($d['Email'], FILTER_VALIDATE_EMAIL)) $e['Email'] = 'Enter a valid work email.';
	if ($d['Phone'] !== '' && !preg_match('/^[0-9+\-\s]{9,20}$/', $d['Phone'])) $e['Phone'] = 'Enter a valid phone number.';
	if (mb_strlen($d['Plate_number']) > 50) $e['Plate_number'] = 'Too long.';
	if (!isset($e['Email']) && q_one('SELECT 1 FROM Staff WHERE Email = ? AND Staff_ID <> ?', [$d['Email'], $ignoreId ?? 0])) {
		$e['Email'] = 'Another staff member uses this email.';
	}
	if ($e) json_out(false, ['fields' => $e], 'Please check the highlighted fields.', 422);
	$d['Phone'] = $d['Phone'] ?: null;
	$d['Plate_number'] = $d['Plate_number'] ?: null;
	return $d;
}

function role_input(): string
{
	$role = input('Role');
	if (!in_array($role, ASSIGNABLE_ROLES, true)) {
		json_out(false, ['fields' => ['Role' => 'Pick Manager, Kitchen Staff or Courier.']], 'Invalid role.', 422);
	}
	return $role;
}

switch ($action) {
	case 'list':
		$where = ['1 = 1'];
		$params = [];
		$qtext = input('q');
		if ($qtext !== '') {
			$where[] = '(Name LIKE ? OR Email LIKE ? OR Phone LIKE ? OR Plate_number LIKE ?)';
			$like = '%' . addcslashes($qtext, '%_\\') . '%';
			array_push($params, $like, $like, $like, $like);
		}
		if (in_array(input('role'), STAFF_ROLES, true)) {
			$where[] = 'Role = ?';
			$params[] = input('role');
		}
		if (in_array(input('status'), STAFF_STATUSES, true)) {
			$where[] = 'Status = ?';
			$params[] = input('status');
		}
		if (($from = DateTime::createFromFormat('!Y-m-d', input('from'))) !== false) {
			$where[] = 'Created_at >= ?';
			$params[] = $from->format('Y-m-d 00:00:00');
		}
		if (($to = DateTime::createFromFormat('!Y-m-d', input('to'))) !== false) {
			$where[] = 'Created_at <= ?';
			$params[] = $to->format('Y-m-d 23:59:59');
		}
		// whitelist: the sort key never reaches SQL as user text
		$sorts = [
			'newest'  => 'Created_at DESC, Staff_ID DESC',
			'oldest'  => 'Created_at ASC, Staff_ID ASC',
			'name'    => 'Name ASC',
			'name_za' => 'Name DESC',
			'role'    => "FIELD(Role, 'Super Admin', 'Manager', 'Kitchen Staff', 'Courier'), Name",
			'login'   => 'Last_login_at IS NULL, Last_login_at DESC',
		];
		$order = $sorts[input('sort')] ?? $sorts['newest'];
		$whereSql = implode(' AND ', $where);

		$total = (int) q_one("SELECT COUNT(*) c FROM Staff WHERE $whereSql", $params)['c'];
		$pages = max(1, (int) ceil($total / PER_PAGE));
		$page = min($pages, max(1, (int) input('page', '1')));
		$offset = ($page - 1) * PER_PAGE;
		$rows = q_all('SELECT ' . STAFF_COLUMNS . " FROM Staff WHERE $whereSql ORDER BY $order LIMIT " . PER_PAGE . " OFFSET $offset", $params);

		$counts = array_fill_keys(STAFF_ROLES, 0);
		foreach (q_all("SELECT Role, COUNT(*) c FROM Staff WHERE Status <> 'Deactivated' GROUP BY Role") as $r) $counts[$r['Role']] = (int) $r['c'];

		json_out(true, ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => PER_PAGE, 'counts' => $counts, 'me' => (int) $me['Staff_ID']]);

	case 'create':
		$d = staff_input();
		$role = role_input();
		$password = (string) ($_POST['Password'] ?? '');
		$generated = false;
		if ($password === '') {
			$password = random_password();
			$generated = true;
		} elseif ($p = password_problem($password)) {
			json_out(false, ['fields' => ['Password' => $p]], $p, 422);
		}
		q('INSERT INTO Staff (Name, Email, Phone, Plate_number, Role, Password) VALUES (?, ?, ?, ?, ?, ?)', [
			$d['Name'], $d['Email'], $d['Phone'], $role === 'Courier' ? $d['Plate_number'] : null, $role, password_hash($password, PASSWORD_DEFAULT),
		]);
		json_out(true, ['row' => staff_row((int) db()->insert_id), 'temp_password' => $generated ? $password : null]);

	case 'update':
		$t = editable_target($me);
		$d = staff_input((int) $t['Staff_ID']);
		q('UPDATE Staff SET Name = ?, Email = ?, Phone = ?, Plate_number = ? WHERE Staff_ID = ?', [
			$d['Name'], $d['Email'], $d['Phone'], $t['Role'] === 'Courier' ? $d['Plate_number'] : null, (int) $t['Staff_ID'],
		]);
		json_out(true, ['row' => staff_row((int) $t['Staff_ID'])]);

	case 'set_role':
		$t = editable_target($me);
		$role = role_input();
		q('UPDATE Staff SET Role = ?, Plate_number = IF(? = \'Courier\', Plate_number, NULL) WHERE Staff_ID = ?', [$role, $role, (int) $t['Staff_ID']]);
		json_out(true, ['row' => staff_row((int) $t['Staff_ID'])]);

	case 'set_status':
		$t = editable_target($me);
		$status = input('Status');
		if (!in_array($status, STAFF_STATUSES, true)) json_out(false, null, 'Invalid status.', 422);
		q('UPDATE Staff SET Status = ? WHERE Staff_ID = ?', [$status, (int) $t['Staff_ID']]);
		json_out(true, ['row' => staff_row((int) $t['Staff_ID'])]);

	case 'reset_password':
		// A one-time temporary password is shown to the admin once; only its hash is stored.
		$t = editable_target($me);
		$temp = random_password();
		q('UPDATE Staff SET Password = ? WHERE Staff_ID = ?', [password_hash($temp, PASSWORD_DEFAULT), (int) $t['Staff_ID']]);
		json_out(true, ['row' => staff_row((int) $t['Staff_ID']), 'temp_password' => $temp]);

	default:
		json_out(false, null, 'Unknown action.', 400);
}
