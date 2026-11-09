<?php
/**
 * Customer accounts (AJAX). Managers + Super Admins.
 * POST action = list | set_status
 * Status is derived: Deactivated (Deleted_at set) > Suspended (Suspend = 1) > Active.
 * Nothing is ever hard-deleted, so order history stays intact.
 */
require __DIR__ . '/../../app/bootstrap.php';
require __DIR__ . '/../../app/loyalty.php';

$action = api_start();
require_staff(ADMIN_ROLES);
const MEMBERS_PER_PAGE = 10;

const MEMBER_SELECT = "SELECT u.User_ID, u.User_Name, u.Email, u.Contain_number, u.Profile_Image, u.Created_at,
	CASE WHEN u.Deleted_at IS NOT NULL THEN 'Deactivated' WHEN u.Suspend = 1 THEN 'Suspended' ELSE 'Active' END AS status,
	(SELECT COUNT(*) FROM Payment p WHERE p.User_ID = u.User_ID AND p.Payment_status = 'SUCCEEDED') AS orders,
	(SELECT COALESCE(SUM(Payment_amount), 0) FROM Payment p WHERE p.User_ID = u.User_ID AND p.Payment_status = 'SUCCEEDED') AS spent,
	(SELECT MAX(Created_at) FROM Payment p WHERE p.User_ID = u.User_ID) AS last_order
	FROM User u";

function member_row(int $id): array
{
	$r = q_one(MEMBER_SELECT . ' WHERE u.User_ID = ?', [$id]);
	if (!$r) json_out(false, null, 'Member not found.', 404);
	return decorate($r);
}

function decorate(array $r): array
{
	$r['tier'] = loyalty_for((float) $r['spent'])['tier'];
	$r['avatar'] = $r['Profile_Image'] ? url(preg_replace('#^(\.\./)+#', '', $r['Profile_Image'])) : null;
	unset($r['Profile_Image']);
	return $r;
}

switch ($action) {
	case 'list':
		$where = ['1 = 1'];
		$params = [];
		if (($qtext = input('q')) !== '') {
			$like = '%' . addcslashes($qtext, '%_\\') . '%';
			$where[] = '(u.User_Name LIKE ? OR u.Email LIKE ? OR u.Contain_number LIKE ?)';
			array_push($params, $like, $like, $like);
		}
		$status = input('status');
		if ($status === 'Active') $where[] = 'u.Deleted_at IS NULL AND u.Suspend = 0';
		if ($status === 'Suspended') $where[] = 'u.Deleted_at IS NULL AND u.Suspend = 1';
		if ($status === 'Deactivated') $where[] = 'u.Deleted_at IS NOT NULL';
		$sorts = ['newest' => 'u.Created_at DESC, u.User_ID DESC', 'name' => 'u.User_Name ASC', 'spent' => 'spent DESC', 'orders' => 'orders DESC', 'recent' => 'last_order IS NULL, last_order DESC'];
		$order = $sorts[input('sort')] ?? $sorts['newest'];
		$w = implode(' AND ', $where);

		$total = (int) q_one("SELECT COUNT(*) c FROM User u WHERE $w", $params)['c'];
		$pages = max(1, (int) ceil($total / MEMBERS_PER_PAGE));
		$page = min($pages, max(1, (int) input('page', '1')));
		$rows = q_all(MEMBER_SELECT . " WHERE $w ORDER BY $order LIMIT " . MEMBERS_PER_PAGE . ' OFFSET ' . (($page - 1) * MEMBERS_PER_PAGE), $params);
		$counts = q_one("SELECT SUM(Deleted_at IS NULL AND Suspend = 0) active, SUM(Deleted_at IS NULL AND Suspend = 1) suspended, SUM(Deleted_at IS NOT NULL) deactivated FROM User");
		json_out(true, ['rows' => array_map('decorate', $rows), 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => MEMBERS_PER_PAGE,
			'counts' => array_map('intval', $counts)]);

	case 'set_status':
		$id = (int) input('User_ID');
		member_row($id);
		switch (input('Status')) {
			case 'Active':
				q('UPDATE User SET Suspend = 0, Deleted_at = NULL WHERE User_ID = ?', [$id]);
				break;
			case 'Suspended':
				q('UPDATE User SET Suspend = 1 WHERE User_ID = ?', [$id]);
				break;
			case 'Deactivated':
				q('UPDATE User SET Deleted_at = COALESCE(Deleted_at, NOW()) WHERE User_ID = ?', [$id]);
				break;
			default:
				json_out(false, null, 'Invalid status.', 422);
		}
		json_out(true, member_row($id));

	default:
		json_out(false, null, 'Unknown action.', 400);
}
