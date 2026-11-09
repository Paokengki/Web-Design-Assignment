<?php
/**
 * Promo code rules used at checkout (Module 2 codes become real discounts here).
 * Returns the promotion row when valid, or a message explaining why not.
 */
function promo_check(string $code, float $subtotal, ?int $userId): array|string
{
	$p = q_one('SELECT * FROM Promotion WHERE Code = ?', [strtoupper(trim($code))]);
	if (!$p) return 'That code doesn’t exist.';
	if (!(int) $p['Is_active']) return 'That code is paused right now.';
	$now = time();
	if (strtotime($p['Starts_at']) > $now) return 'That code starts on ' . date('j M', strtotime($p['Starts_at'])) . '.';
	if (strtotime($p['Expires_at']) <= $now) return 'That code has expired.';
	if ($p['Max_redemptions'] !== null && (int) $p['Redemption_count'] >= (int) $p['Max_redemptions']) return 'That code has been fully redeemed.';
	if ($subtotal < (float) $p['Min_spend']) return 'Spend at least RM' . number_format((float) $p['Min_spend'], 2) . ' to use this code.';
	if ($userId && q_one('SELECT 1 FROM Promotion_Redemption WHERE User_ID = ? AND Promo_ID = ?', [$userId, (int) $p['Promo_ID']])) {
		return 'You’ve already used this code.';
	}
	return $p;
}

/**
 * Vouchers shown as cards at checkout: every live code (active, started, not expired, not used up)
 * that this customer hasn't used yet. Codes below the minimum spend are returned too, flagged
 * `eligible = false` with how much more to spend, so the card can show it greyed out.
 */
function promo_available(int $userId, float $subtotal): array
{
	$rows = q_all('SELECT p.Promo_ID, p.Code, p.Title, p.Description, p.Discount_percent, p.Min_spend, p.Expires_at, p.Max_redemptions, p.Redemption_count
		FROM Promotion p
		WHERE p.Is_active = 1 AND p.Starts_at <= NOW() AND p.Expires_at > NOW()
		  AND (p.Max_redemptions IS NULL OR p.Redemption_count < p.Max_redemptions)
		  AND NOT EXISTS (SELECT 1 FROM Promotion_Redemption r WHERE r.Promo_ID = p.Promo_ID AND r.User_ID = ?)
		ORDER BY p.Discount_percent DESC, p.Expires_at ASC', [$userId]);

	$out = [];
	foreach ($rows as $r) {
		$min = (float) $r['Min_spend'];
		$out[] = [
			'code'      => $r['Code'],
			'title'     => $r['Title'],
			'detail'    => (string) $r['Description'],
			'percent'   => (float) $r['Discount_percent'],
			'min_spend' => $min,
			'expires'   => date('c', strtotime($r['Expires_at'])),
			'left'      => $r['Max_redemptions'] === null ? null : (int) $r['Max_redemptions'] - (int) $r['Redemption_count'],
			'eligible'  => $subtotal >= $min,
			'short_by'  => round(max(0, $min - $subtotal), 2),
			'saves'     => round($subtotal * (float) $r['Discount_percent'] / 100, 2),
		];
	}
	return $out;
}
