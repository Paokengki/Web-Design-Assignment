<?php
/**
 * Café registration applications (AJAX). Managers + Super Admins.
 * POST action = list | approve | reject
 * Approve creates the Restaurant row and a Kitchen Staff account, then emails the owner the temp password.
 * Place at api/admin/applications.php
 */
require __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/services/cafe_mailer.php';

$action = api_start();
$me = require_staff(ADMIN_ROLES);

const APP_STATUSES = ['Pending', 'Approved', 'Rejected'];
const APPS_PER_PAGE = 10;

function app_row(int $id): array
{
	$r = q_one('SELECT a.*, s.Name AS Reviewed_by_name FROM Restaurant_Application a
		LEFT JOIN Staff s ON s.Staff_ID = a.Reviewed_By WHERE a.Application_ID = ?', [$id]);
	if (!$r) json_out(false, null, 'Application not found.', 404);
	return $r;
}

function pending_app(): array
{
	$a = app_row((int) input('Application_ID'));
	if ($a['Status'] !== 'Pending') json_out(false, null, 'This application was already ' . strtolower($a['Status']) . '.', 409);
	return $a;
}

switch ($action) {
	case 'list':
		$where = ['1 = 1'];
		$params = [];
		if (($qtext = input('q')) !== '') {
			$like = '%' . addcslashes($qtext, '%_\\') . '%';
			$where[] = '(a.Name LIKE ? OR a.Owner_Name LIKE ? OR a.Email LIKE ?)';
			array_push($params, $like, $like, $like);
		}
		if (in_array(input('status'), APP_STATUSES, true)) {
			$where[] = 'a.Status = ?';
			$params[] = input('status');
		}
		$w = implode(' AND ', $where);

		$total = (int) q_one("SELECT COUNT(*) c FROM Restaurant_Application a WHERE $w", $params)['c'];
		$pages = max(1, (int) ceil($total / APPS_PER_PAGE));
		$page = min($pages, max(1, (int) input('page', '1')));
		$rows = q_all("SELECT a.* FROM Restaurant_Application a WHERE $w ORDER BY a.Created_At DESC, a.Application_ID DESC LIMIT "
			. APPS_PER_PAGE . ' OFFSET ' . (($page - 1) * APPS_PER_PAGE), $params);

		$counts = array_fill_keys(APP_STATUSES, 0);
		foreach (q_all('SELECT Status, COUNT(*) c FROM Restaurant_Application GROUP BY Status') as $r) $counts[$r['Status']] = (int) $r['c'];

		json_out(true, ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => APPS_PER_PAGE, 'counts' => $counts]);

	case 'approve':
		$a = pending_app();
		$email = strtolower($a['Email']);

		// Pre-checks so we never half-create anything.
		if (q_one('SELECT 1 FROM Restaurant WHERE Name = ?', [$a['Name']])) {
			json_out(false, null, 'A café named “' . $a['Name'] . '” already exists. Reject this application or ask the owner to change the name.', 409);
		}
		if (q_one('SELECT 1 FROM Staff WHERE Email = ?', [$email])) {
			json_out(false, null, 'A staff account already uses ' . $email . '.', 409);
		}

		$temp = random_password();
		db()->begin_transaction();
		try {
			q('INSERT INTO Restaurant (Name, Restaurant_type, Email, Contain_number, Address) VALUES (?, ?, ?, ?, ?)',
				[$a['Name'], $a['Restaurant_type'], $a['Email'], $a['Contain_number'], $a['Address']]);
			$rid = (int) db()->insert_id;

			q('INSERT INTO Staff (Name, Email, Phone, Assigned_Restaurant_ID, Role, Password) VALUES (?, ?, ?, ?, ?, ?)',
				[$a['Owner_Name'], $email, $a['Contain_number'], $rid, 'Kitchen Staff', password_hash($temp, PASSWORD_DEFAULT)]);

			q("UPDATE Restaurant_Application SET Status = 'Approved', Reviewed_By = ?, Reviewed_At = NOW(), Restaurant_ID = ?, Reject_reason = NULL
				WHERE Application_ID = ? AND Status = 'Pending'", [(int) $me['Staff_ID'], $rid, (int) $a['Application_ID']]);
			db()->commit();
		} catch (Throwable $t) {
			db()->rollback();
			json_out(false, null, 'Could not approve this application. Nothing was saved.', 500);
		}
		// Email the sign-in details. A mail problem must never undo the approval, so the password is only
		// returned to the admin screen when the email could not be sent (so it can be passed on by hand).
		$emailed = cafe_send_application_approved($a['Email'], $a['Owner_Name'], $a['Name'], $temp);
		json_out(true, ['row' => app_row((int) $a['Application_ID']), 'emailed' => $emailed, 'temp_password' => $emailed ? null : $temp, 'staff_email' => $email]);

	case 'reject':
		$a = pending_app();
		$reason = mb_substr(input('Reject_reason'), 0, 255);
		q("UPDATE Restaurant_Application SET Status = 'Rejected', Reject_reason = ?, Reviewed_By = ?, Reviewed_At = NOW()
			WHERE Application_ID = ? AND Status = 'Pending'", [$reason === '' ? null : $reason, (int) $me['Staff_ID'], (int) $a['Application_ID']]);
		json_out(true, ['row' => app_row((int) $a['Application_ID'])]);

	default:
		json_out(false, null, 'Unknown action.', 400);
}
