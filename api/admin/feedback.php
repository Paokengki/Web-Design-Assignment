<?php
/**
 * Feedback inbox (AJAX) — messages from the contact form. Managers + Super Admins.
 * POST action = list | read | unread | delete
 */
require __DIR__ . '/../../app/bootstrap.php';

$action = api_start();
require_staff(ADMIN_ROLES);

function unread_count(): int
{
	return (int) q_one('SELECT COUNT(*) c FROM contact_us WHERE Is_read = 0')['c'];
}

$id = (int) input('id');
switch ($action) {
	case 'list':
		$where = ['1 = 1'];
		$params = [];
		if (($qtext = input('q')) !== '') {
			$like = '%' . addcslashes($qtext, '%_\\') . '%';
			$where[] = '(name LIKE ? OR email LIKE ? OR message LIKE ?)';
			array_push($params, $like, $like, $like);
		}
		if (input('filter') === 'unread') $where[] = 'Is_read = 0';
		$rows = q_all('SELECT id, name, email, phone, message, created_at, Is_read FROM contact_us WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC, id DESC LIMIT 200', $params);
		json_out(true, ['rows' => $rows, 'unread' => unread_count()]);

	case 'read':
	case 'unread':
		q('UPDATE contact_us SET Is_read = ? WHERE id = ?', [$action === 'read' ? 1 : 0, $id]);
		json_out(true, ['id' => $id, 'Is_read' => $action === 'read' ? 1 : 0, 'unread' => unread_count()]);

	case 'delete':
		q('DELETE FROM contact_us WHERE id = ?', [$id]);
		json_out(true, ['id' => $id, 'unread' => unread_count()]);

	default:
		json_out(false, null, 'Unknown action.', 400);
}
