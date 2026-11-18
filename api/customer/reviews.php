<?php
/**
 * Customer reviews (AJAX). Signed-in customers only.
 * POST action = list | create | update
 *   list:   { to_review: delivered orders not yet reviewed, mine: this customer's reviews incl. admin replies }
 *   create: Payment_ID, Rating (1-5), Comment — only for a Delivered order of this customer, once per order
 *   update: Review_ID, Rating, Comment — only within 24 hours of posting, and not after a moderator hid it
 * Place at api/customer/reviews.php
 */
require __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/services/reviews.php';

$action = api_start();
$user = current_user();
if (!$user) json_out(false, null, 'Please sign in to leave a review.', 401);
$uid = (int) $user['User_ID'];

const MY_REVIEW_SELECT = "SELECT rv.Review_ID, rv.Payment_ID, rv.Restaurant_ID, rv.Rating, rv.Comment, rv.Issue_status,
	rv.Admin_reply, rv.Replied_at, rv.Is_hidden, rv.Created_at, rv.Edited_at,
	COALESCE(r.Name, 'Multiple cafés') AS cafe_name,
	(rv.Is_hidden = 0 AND rv.Created_at > NOW() - INTERVAL " . REVIEW_EDIT_HOURS . " HOUR) AS can_edit,
	GREATEST(0, TIMESTAMPDIFF(MINUTE, NOW(), rv.Created_at + INTERVAL " . REVIEW_EDIT_HOURS . " HOUR)) AS minutes_left
	FROM Review rv LEFT JOIN Restaurant r ON r.Restaurant_ID = rv.Restaurant_ID";

function my_review(int $id, int $uid): array
{
	$r = q_one(MY_REVIEW_SELECT . ' WHERE rv.Review_ID = ? AND rv.User_ID = ?', [$id, $uid]);
	if (!$r) json_out(false, null, 'Review not found.', 404);
	return $r;
}

/** The customer's own, successfully paid order that has been delivered. */
function reviewable_order(int $paymentId, int $uid): array
{
	$p = q_one("SELECT p.Payment_ID, p.Restaurant_ID FROM Payment p
		JOIN Order_Tracking ot ON ot.Payment_ID = p.Payment_ID
		WHERE p.Payment_ID = ? AND p.User_ID = ? AND p.Payment_status = 'SUCCEEDED' AND ot.Order_status = 'Delivered'", [$paymentId, $uid]);
	if (!$p) json_out(false, null, 'Only your delivered orders can be reviewed.', 403);
	return $p;
}

switch ($action) {
	case 'list':
		$toReview = q_all("SELECT p.Payment_ID, p.Payment_amount, p.Created_at, ot.Updated_at AS Delivered_at,
			COALESCE(r.Name, 'Multiple cafés') AS cafe_name,
			(SELECT GROUP_CONCAT(CONCAT(pi.Quantity, '× ', pi.Item_name) SEPARATOR ', ') FROM Payment_Item pi WHERE pi.Payment_ID = p.Payment_ID) AS items
			FROM Payment p
			JOIN Order_Tracking ot ON ot.Payment_ID = p.Payment_ID AND ot.Order_status = 'Delivered'
			LEFT JOIN Restaurant r ON r.Restaurant_ID = p.Restaurant_ID
			LEFT JOIN Review rv ON rv.Payment_ID = p.Payment_ID
			WHERE p.User_ID = ? AND p.Payment_status = 'SUCCEEDED' AND rv.Review_ID IS NULL
			ORDER BY ot.Updated_at DESC LIMIT 50", [$uid]);
		$mine = q_all(MY_REVIEW_SELECT . ' WHERE rv.User_ID = ? ORDER BY rv.Created_at DESC, rv.Review_ID DESC', [$uid]);
		json_out(true, ['to_review' => $toReview, 'mine' => $mine, 'edit_hours' => REVIEW_EDIT_HOURS]);

	case 'create':
		$pid = (int) input('Payment_ID');
		$p = reviewable_order($pid, $uid);
		[$rating, $comment] = review_input();
		if (q_one('SELECT 1 FROM Review WHERE Payment_ID = ?', [$pid])) json_out(false, null, 'You have already reviewed this order.', 409);
		$cafe = review_cafe_id($p);
		q('INSERT INTO Review (Payment_ID, User_ID, Restaurant_ID, Rating, Comment) VALUES (?, ?, ?, ?, ?)', [$pid, $uid, $cafe, $rating, $comment]);
		$id = (int) db()->insert_id;
		review_refresh_rating($cafe);
		json_out(true, my_review($id, $uid));

	case 'update':
		$rv = my_review((int) input('Review_ID'), $uid);
		if (!(int) $rv['can_edit']) {
			json_out(false, null, (int) $rv['Is_hidden'] ? 'This review was hidden by our team and can no longer be edited.' : 'The ' . REVIEW_EDIT_HOURS . '-hour edit window has passed.', 403);
		}
		[$rating, $comment] = review_input();
		q('UPDATE Review SET Rating = ?, Comment = ?, Edited_at = NOW() WHERE Review_ID = ? AND User_ID = ?', [$rating, $comment, (int) $rv['Review_ID'], $uid]);
		review_refresh_rating($rv['Restaurant_ID'] ? (int) $rv['Restaurant_ID'] : null);
		json_out(true, my_review((int) $rv['Review_ID'], $uid));

	default:
		json_out(false, null, 'Unknown action.', 400);
}
