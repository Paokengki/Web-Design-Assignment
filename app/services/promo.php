<?php
/**
 * Voucher rules.
 *
 * Two kinds live in the Promotion table:
 *   FREE CODE  (Points_cost = 0)  — every member can apply it at checkout, once.
 *   REWARD     (Points_cost > 0)  — a member first redeems it with loyalty points (reward_redeem);
 *                                   that creates a User_Voucher row, which is then spent at checkout.
 *
 * A voucher is "released" once Starts_at has passed (admins can schedule a future release).
 */
require_once __DIR__ . '/../loyalty.php';

function promo_is_reward(array $p): bool
{
	return (int) $p['Points_cost'] > 0;
}

/** The member's oldest unused voucher for this promotion, or null. */
function user_voucher_unused(int $userId, int $promoId): ?array
{
	return q_one('SELECT * FROM User_Voucher WHERE User_ID = ? AND Promo_ID = ? AND Used_at IS NULL ORDER BY Redeemed_at LIMIT 1', [$userId, $promoId]);
}

/**
 * Can this code be applied to the cart right now?
 * Returns the Promotion row when valid, or a message explaining why not.
 */
function promo_check(string $code, float $subtotal, ?int $userId): array|string
{
	$p = q_one('SELECT * FROM Promotion WHERE Code = ?', [strtoupper(trim($code))]);
	if (!$p) return 'That code doesn’t exist.';
	if (!(int) $p['Is_active']) return 'That voucher is paused right now.';
	$now = time();
	if (strtotime($p['Starts_at']) > $now) return 'That voucher is released on ' . date('j M', strtotime($p['Starts_at'])) . '.';
	if (strtotime($p['Expires_at']) <= $now) return 'That voucher has expired.';
	if ($subtotal < (float) $p['Min_spend']) return 'Spend at least RM' . number_format((float) $p['Min_spend'], 2) . ' to use this voucher.';

	if (promo_is_reward($p)) {
		// Rewards: the member must own an unused one. Stock and limits were checked when they redeemed it.
		if (!$userId || !user_voucher_unused($userId, (int) $p['Promo_ID'])) return 'Redeem this reward with your points first.';
		return $p;
	}

	// Free codes: global stock + once per member.
	if ($p['Max_redemptions'] !== null && (int) $p['Redemption_count'] >= (int) $p['Max_redemptions']) return 'That code has been fully redeemed.';
	if ($userId && q_one('SELECT 1 FROM Promotion_Redemption WHERE User_ID = ? AND Promo_ID = ?', [$userId, (int) $p['Promo_ID']])) {
		return 'You’ve already used this code.';
	}
	return $p;
}

/**
 * Voucher cards at checkout: the member's unused rewards first, then every live free code they
 * haven't used. Ones below their minimum spend are flagged `eligible = false`.
 */
function promo_available(int $userId, float $subtotal): array
{
	$rewards = q_all('SELECT p.*, COUNT(*) AS owned FROM User_Voucher uv JOIN Promotion p ON p.Promo_ID = uv.Promo_ID
		WHERE uv.User_ID = ? AND uv.Used_at IS NULL AND p.Is_active = 1 AND p.Starts_at <= NOW() AND p.Expires_at > NOW()
		GROUP BY p.Promo_ID ORDER BY p.Discount_percent DESC', [$userId]);

	$free = q_all('SELECT p.*, 0 AS owned FROM Promotion p
		WHERE p.Points_cost = 0 AND p.Is_active = 1 AND p.Starts_at <= NOW() AND p.Expires_at > NOW()
		  AND (p.Max_redemptions IS NULL OR p.Redemption_count < p.Max_redemptions)
		  AND NOT EXISTS (SELECT 1 FROM Promotion_Redemption r WHERE r.Promo_ID = p.Promo_ID AND r.User_ID = ?)
		ORDER BY p.Discount_percent DESC, p.Expires_at ASC', [$userId]);

	$out = [];
	foreach (array_merge($rewards, $free) as $r) {
		$min = (float) $r['Min_spend'];
		$reward = promo_is_reward($r);
		$out[] = [
			'code'      => $r['Code'],
			'title'     => $r['Title'],
			'detail'    => (string) $r['Description'],
			'percent'   => (float) $r['Discount_percent'],
			'min_spend' => $min,
			'expires'   => date('c', strtotime($r['Expires_at'])),
			'kind'      => $reward ? 'reward' : 'free',
			'owned'     => (int) $r['owned'],
			'left'      => $reward || $r['Max_redemptions'] === null ? null : (int) $r['Max_redemptions'] - (int) $r['Redemption_count'],
			'eligible'  => $subtotal >= $min,
			'short_by'  => round(max(0, $min - $subtotal), 2),
			'saves'     => round($subtotal * (float) $r['Discount_percent'] / 100, 2),
		];
	}
	return $out;
}

/* ====================================================================== rewards (points → vouchers) */

/**
 * Rewards catalogue for the dashboard: every RELEASED reward voucher, with what this member
 * can do about it (`can_redeem` + `reason` when they can't).
 */
function rewards_catalog(int $userId): array
{
	$balance = points_balance($userId);
	$rows = q_all('SELECT p.*,
			(SELECT COUNT(*) FROM User_Voucher uv WHERE uv.Promo_ID = p.Promo_ID AND uv.User_ID = ?) AS mine
		FROM Promotion p
		WHERE p.Points_cost > 0 AND p.Is_active = 1 AND p.Starts_at <= NOW() AND p.Expires_at > NOW()
		ORDER BY p.Points_cost ASC, p.Discount_percent DESC', [$userId]);

	$out = [];
	foreach ($rows as $r) {
		$left = $r['Max_redemptions'] === null ? null : max(0, (int) $r['Max_redemptions'] - (int) $r['Redemption_count']);
		$cost = (int) $r['Points_cost'];
		$reason = null;
		if ($left === 0) $reason = 'All claimed';
		elseif ((int) $r['mine'] >= (int) $r['Per_user_limit']) $reason = 'Limit reached';
		elseif ($balance < $cost) $reason = 'Need ' . number_format($cost - $balance) . ' more pts';
		$out[] = [
			'id'         => (int) $r['Promo_ID'],
			'code'       => $r['Code'],
			'title'      => $r['Title'],
			'detail'     => (string) $r['Description'],
			'percent'    => (float) $r['Discount_percent'],
			'min_spend'  => (float) $r['Min_spend'],
			'cost'       => $cost,
			'expires'    => date('c', strtotime($r['Expires_at'])),
			'left'       => $left,
			'mine'       => (int) $r['mine'],
			'limit'      => (int) $r['Per_user_limit'],
			'can_redeem' => $reason === null,
			'reason'     => $reason,
			'progress'   => $cost > 0 ? (int) min(100, floor($balance / $cost * 100)) : 100,
		];
	}
	return $out;
}

/** "My vouchers": everything this member has redeemed, newest first, with a status. */
function rewards_owned(int $userId): array
{
	$rows = q_all('SELECT uv.User_Voucher_ID, uv.Points_spent, uv.Redeemed_at, uv.Used_at, uv.Used_payment_id,
			p.Code, p.Title, p.Discount_percent, p.Min_spend, p.Expires_at, p.Is_active
		FROM User_Voucher uv JOIN Promotion p ON p.Promo_ID = uv.Promo_ID
		WHERE uv.User_ID = ? ORDER BY (uv.Used_at IS NULL) DESC, uv.Redeemed_at DESC LIMIT 50', [$userId]);
	return array_map(function ($r) {
		$status = $r['Used_at'] !== null ? 'used' : (strtotime($r['Expires_at']) <= time() ? 'expired' : ((int) $r['Is_active'] ? 'ready' : 'paused'));
		return [
			'id' => (int) $r['User_Voucher_ID'], 'code' => $r['Code'], 'title' => $r['Title'],
			'percent' => (float) $r['Discount_percent'], 'min_spend' => (float) $r['Min_spend'],
			'points' => (int) $r['Points_spent'], 'redeemed' => date('c', strtotime($r['Redeemed_at'])),
			'expires' => date('c', strtotime($r['Expires_at'])), 'status' => $status,
			'order' => $r['Used_payment_id'] !== null ? (int) $r['Used_payment_id'] : null,
		];
	}, $rows);
}

/**
 * Redeem a reward with points — one atomic transaction.
 *
 * Locks the member's row first (SELECT … FOR UPDATE), so two redemptions by the same member
 * run one after the other and the balance can never be spent twice. Then locks the promotion
 * row so the last unit of limited stock cannot be claimed by two members at once.
 *
 * Throws InvalidArgumentException with a message for the member when a rule fails.
 * Returns ['voucher_id', 'balance', 'code'].
 */
function reward_redeem(int $userId, int $promoId): array
{
	$db = db();
	$db->begin_transaction();
	try {
		// 1. Serialise this member's redemptions.
		if (!q_one('SELECT User_ID FROM User WHERE User_ID = ? AND Deleted_at IS NULL AND Suspend = 0 FOR UPDATE', [$userId])) {
			throw new InvalidArgumentException('Your account can’t redeem rewards right now.');
		}

		// 2. Lock the reward and re-check every rule inside the transaction.
		$p = q_one('SELECT * FROM Promotion WHERE Promo_ID = ? FOR UPDATE', [$promoId]);
		if (!$p || !promo_is_reward($p)) throw new InvalidArgumentException('That reward doesn’t exist.');
		if (!(int) $p['Is_active']) throw new InvalidArgumentException('That reward is paused right now.');
		$now = time();
		if (strtotime($p['Starts_at']) > $now) throw new InvalidArgumentException('That reward hasn’t been released yet.');
		if (strtotime($p['Expires_at']) <= $now) throw new InvalidArgumentException('That reward has expired.');
		if ($p['Max_redemptions'] !== null && (int) $p['Redemption_count'] >= (int) $p['Max_redemptions']) {
			throw new InvalidArgumentException('Sorry, every one of these has been claimed.');
		}
		$mine = (int) q_one('SELECT COUNT(*) c FROM User_Voucher WHERE User_ID = ? AND Promo_ID = ?', [$userId, $promoId])['c'];
		if ($mine >= (int) $p['Per_user_limit']) {
			throw new InvalidArgumentException('You’ve reached the limit for this reward (' . (int) $p['Per_user_limit'] . ' per member).');
		}

		// 3. Enough points? (balance is computed inside the lock)
		$cost = (int) $p['Points_cost'];
		$balance = points_balance($userId);
		if ($balance < $cost) {
			throw new InvalidArgumentException('You need ' . number_format($cost - $balance) . ' more points for this reward.');
		}

		// 4. Deduct the points (the User_Voucher row IS the deduction) and take one from stock.
		q('INSERT INTO User_Voucher (User_ID, Promo_ID, Points_spent) VALUES (?, ?, ?)', [$userId, $promoId, $cost]);
		$voucherId = (int) $db->insert_id;
		q('UPDATE Promotion SET Redemption_count = Redemption_count + 1 WHERE Promo_ID = ?', [$promoId]);

		$db->commit();
		return ['voucher_id' => $voucherId, 'balance' => $balance - $cost, 'code' => $p['Code'], 'title' => $p['Title']];
	} catch (Throwable $e) {
		$db->rollback();
		throw $e;
	}
}
