<?php
/** Shared helpers for the Feedback & Ratings module. Place at app/services/reviews.php */

const REVIEW_EDIT_HOURS = 24;
const REVIEW_STATUSES = ['Pending', 'Investigating', 'Resolved'];

/** Which café a review belongs to: the order's café, or NULL when the order spans several cafés. */
function review_cafe_id(array $payment): ?int
{
	if (!empty($payment['Restaurant_ID'])) return (int) $payment['Restaurant_ID'];
	$rows = q_all('SELECT DISTINCT Restaurant_ID FROM Order_Tracking_Item WHERE Payment_ID = ?', [(int) $payment['Payment_ID']]);
	return count($rows) === 1 ? (int) $rows[0]['Restaurant_ID'] : null;
}

/**
 * Keep Restaurant.Rating equal to the average of that café's visible reviews.
 * (Remove the calls to this function if you do NOT want reviews to change the café's displayed rating.)
 */
function review_refresh_rating(?int $restaurantId): void
{
	if (!$restaurantId) return;
	$r = q_one('SELECT COUNT(*) n, AVG(Rating) a FROM Review WHERE Restaurant_ID = ? AND Is_hidden = 0', [$restaurantId]);
	if ((int) $r['n'] > 0) {
		q('UPDATE Restaurant SET Rating = ? WHERE Restaurant_ID = ?', [round((float) $r['a'], 2), $restaurantId]);
	}
}

/** Validate Rating + Comment from the request, or answer 422. Returns [rating, comment]. */
function review_input(): array
{
	$rating = input('Rating');
	$comment = input('Comment');
	$e = [];
	if (!ctype_digit($rating) || (int) $rating < 1 || (int) $rating > 5) $e['Rating'] = 'Choose 1 to 5 stars.';
	if (mb_strlen($comment) < 10) $e['Comment'] = 'Please write at least 10 characters.';
	elseif (mb_strlen($comment) > 1000) $e['Comment'] = 'Keep it under 1000 characters.';
	if ($e) json_out(false, ['fields' => $e], 'Please check the highlighted fields.', 422);
	return [(int) $rating, $comment];
}
