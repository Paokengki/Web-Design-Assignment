<?php
/**
 * Reviews & ratings (AJAX). Managers + Super Admins.
 * POST action = list | reply | set_status | set_hidden
 *   list:       filters q (customer name / email / text), rating, status, visibility (visible|hidden), cafe, from, to, sort, page
 *   reply:      Review_ID, Admin_reply, [Issue_status]   official response shown to the customer
 *   set_status: Review_ID, Status                         Pending | Investigating | Resolved
 *   set_hidden: Review_ID, Hidden (1 = hide, 0 = restore) soft delete / moderation — rows are never removed
 * Place at api/admin/reviews.php
 */
require __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/services/reviews.php';

$action = api_start();
$me = require_staff(ADMIN_ROLES);
const REVIEWS_PER_PAGE = 10;

const ADMIN_REVIEW_SELECT = "SELECT rv.Review_ID, rv.Payment_ID, rv.Restaurant_ID, rv.User_ID, rv.Rating, rv.Comment, rv.Issue_status,
	rv.Admin_reply, rv.Replied_at, rv.Is_hidden, rv.Hidden_at, rv.Created_at, rv.Edited_at,
	u.User_Name AS customer, u.Email AS customer_email,
	COALESCE(r.Name, 'Multiple cafés') AS cafe_name,
	st.Name AS Replied_by_name, hs.Name AS Hidden_by_name
	FROM Review rv
	JOIN User u ON u.User_ID = rv.User_ID
	LEFT JOIN Restaurant r ON r.Restaurant_ID = rv.Restaurant_ID
	LEFT JOIN Staff st ON st.Staff_ID = rv.Replied_by
	LEFT JOIN Staff hs ON hs.Staff_ID = rv.Hidden_by";

function review_row(int $id): array
{
	$r = q_one(ADMIN_REVIEW_SELECT . ' WHERE rv.Review_ID = ?', [$id]);
	if (!$r) json_out(false, null, 'Review not found.', 404);
	return $r;
}

switch ($action) {
	case 'list':
		$where = ['1 = 1'];
		$params = [];
		if (($qtext = input('q')) !== '') {
			$like = '%' . addcslashes($qtext, '%_\\') . '%';
			$where[] = '(u.User_Name LIKE ? OR u.Email LIKE ? OR rv.Comment LIKE ?)';
			array_push($params, $like, $like, $like);
		}
		if (ctype_digit(input('rating')) && (int) input('rating') >= 1 && (int) input('rating') <= 5) {
			$where[] = 'rv.Rating = ?';
			$params[] = (int) input('rating');
		}
		if (in_array(input('status'), REVIEW_STATUSES, true)) {
			$where[] = 'rv.Issue_status = ?';
			$params[] = input('status');
		}
		if (input('visibility') === 'visible') $where[] = 'rv.Is_hidden = 0';
		if (input('visibility') === 'hidden') $where[] = 'rv.Is_hidden = 1';
		if (ctype_digit(input('cafe')) && (int) input('cafe') > 0) {
			$where[] = 'rv.Restaurant_ID = ?';
			$params[] = (int) input('cafe');
		}
		if (($from = DateTime::createFromFormat('!Y-m-d', input('from'))) !== false) {
			$where[] = 'rv.Created_at >= ?';
			$params[] = $from->format('Y-m-d 00:00:00');
		}
		if (($to = DateTime::createFromFormat('!Y-m-d', input('to'))) !== false) {
			$where[] = 'rv.Created_at <= ?';
			$params[] = $to->format('Y-m-d 23:59:59');
		}
		// whitelist: the sort key never reaches SQL as user text
		$sorts = [
			'newest'  => 'rv.Created_at DESC, rv.Review_ID DESC',
			'oldest'  => 'rv.Created_at ASC, rv.Review_ID ASC',
			'lowest'  => 'rv.Rating ASC, rv.Created_at DESC',
			'highest' => 'rv.Rating DESC, rv.Created_at DESC',
		];
		$order = $sorts[input('sort')] ?? $sorts['newest'];
		$w = implode(' AND ', $where);

		$total = (int) q_one("SELECT COUNT(*) c FROM Review rv JOIN User u ON u.User_ID = rv.User_ID WHERE $w", $params)['c'];
		$pages = max(1, (int) ceil($total / REVIEWS_PER_PAGE));
		$page = min($pages, max(1, (int) input('page', '1')));
		$rows = q_all(ADMIN_REVIEW_SELECT . " WHERE $w ORDER BY $order LIMIT " . REVIEWS_PER_PAGE . ' OFFSET ' . (($page - 1) * REVIEWS_PER_PAGE), $params);

		$s = q_one("SELECT COUNT(*) total,
			COALESCE(AVG(CASE WHEN Is_hidden = 0 THEN Rating END), 0) avg_rating,
			SUM(Issue_status = 'Pending' AND Is_hidden = 0) pending,
			SUM(Issue_status = 'Investigating' AND Is_hidden = 0) investigating,
			SUM(Issue_status = 'Resolved' AND Is_hidden = 0) resolved,
			SUM(Is_hidden = 1) hidden FROM Review");
		$stats = ['total' => (int) $s['total'], 'avg_rating' => round((float) $s['avg_rating'], 1), 'pending' => (int) $s['pending'],
			'investigating' => (int) $s['investigating'], 'resolved' => (int) $s['resolved'], 'hidden' => (int) $s['hidden']];

		json_out(true, ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => REVIEWS_PER_PAGE,
			'stats' => $stats, 'cafes' => q_all('SELECT Restaurant_ID, Name FROM Restaurant ORDER BY Name')]);

	case 'reply':
		$rv = review_row((int) input('Review_ID'));
		$reply = input('Admin_reply');
		$status = input('Issue_status');
		$e = [];
		if ($reply === '') $e['Admin_reply'] = 'Write a reply for the customer.';
		elseif (mb_strlen($reply) > 1000) $e['Admin_reply'] = 'Keep it under 1000 characters.';
		if ($status !== '' && !in_array($status, REVIEW_STATUSES, true)) $e['Issue_status'] = 'Pick a status.';
		if ($e) json_out(false, ['fields' => $e], 'Please check the highlighted fields.', 422);
		q('UPDATE Review SET Admin_reply = ?, Replied_by = ?, Replied_at = NOW(), Issue_status = COALESCE(?, Issue_status) WHERE Review_ID = ?',
			[$reply, (int) $me['Staff_ID'], $status === '' ? null : $status, (int) $rv['Review_ID']]);
		json_out(true, review_row((int) $rv['Review_ID']));

	case 'set_status':
		$rv = review_row((int) input('Review_ID'));
		if (!in_array(input('Status'), REVIEW_STATUSES, true)) json_out(false, null, 'Invalid status.', 422);
		q('UPDATE Review SET Issue_status = ? WHERE Review_ID = ?', [input('Status'), (int) $rv['Review_ID']]);
		json_out(true, review_row((int) $rv['Review_ID']));

	case 'set_hidden':
		$rv = review_row((int) input('Review_ID'));
		if (input('Hidden') === '1') {
			q('UPDATE Review SET Is_hidden = 1, Hidden_by = ?, Hidden_at = NOW() WHERE Review_ID = ?', [(int) $me['Staff_ID'], (int) $rv['Review_ID']]);
		} else {
			q('UPDATE Review SET Is_hidden = 0, Hidden_by = NULL, Hidden_at = NULL WHERE Review_ID = ?', [(int) $rv['Review_ID']]);
		}
		review_refresh_rating($rv['Restaurant_ID'] ? (int) $rv['Restaurant_ID'] : null);
		json_out(true, review_row((int) $rv['Review_ID']));

	default:
		json_out(false, null, 'Unknown action.', 400);
}
