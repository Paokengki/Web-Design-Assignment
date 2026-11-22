<?php
/**
 * Loyalty rule: 1 point per RM1 of successful payments (rounded down).
 * Points are calculated from the Payment table, so they are always in sync with orders.
 */
const LOYALTY_TIERS = ['Bronze' => 0, 'Silver' => 200, 'Gold' => 500, 'Platinum' => 1000];

function loyalty_for(float $spent): array
{
	$points = (int) floor($spent);
	$tier = 'Bronze';
	$next = null;
	foreach (LOYALTY_TIERS as $name => $min) {
		if ($points >= $min) {
			$tier = $name;
		} else {
			$next = $name;
			break;
		}
	}
	$floor = LOYALTY_TIERS[$tier];
	$ceil = $next ? LOYALTY_TIERS[$next] : $floor;
	return [
		'points'   => $points,
		'tier'     => $tier,
		'next'     => $next,
		'to_next'  => $next ? $ceil - $points : 0,
		'progress' => $next ? (int) round(($points - $floor) / ($ceil - $floor) * 100) : 100,
	];
}

/* ------------------------------------------------------------------ spendable points
 * Earned  = 1 point per RM1 of successful payments, counted once the order is COMPLETED:
 *           Payment.Points_rule 'on_complete' (every order since migration v8) earns when the customer
 *           taps Complete Order (Payment.Completed_at); 'legacy' orders (paid before v8) kept their points.
 *           Refunded / cancelled orders are not SUCCEEDED, so they never earn.
 * Spent   = points used to redeem reward vouchers (User_Voucher.Points_spent).
 * Balance = Earned − Spent. The tier always uses Earned, so redeeming never lowers a member's tier.
 */
const POINTS_EARNED_SQL = "Payment_status = 'SUCCEEDED' AND (Points_rule = 'legacy' OR Completed_at IS NOT NULL)";

function points_earned(int $userId): int
{
	return (int) floor((float) q_one('SELECT COALESCE(SUM(Payment_amount), 0) s FROM Payment WHERE User_ID = ? AND ' . POINTS_EARNED_SQL, [$userId])['s']);
}

/** Points an order is worth / will be worth (1 per RM1 paid). */
function points_for_amount(float $amount): int
{
	return (int) floor($amount);
}

function points_spent(int $userId): int
{
	return (int) q_one('SELECT COALESCE(SUM(Points_spent), 0) s FROM User_Voucher WHERE User_ID = ?', [$userId])['s'];
}

function points_balance(int $userId): int
{
	return max(0, points_earned($userId) - points_spent($userId));
}

/** Everything the dashboard needs: tier info (lifetime) + spendable balance. */
function loyalty_summary(int $userId): array
{
	$earned = points_earned($userId);
	$spent = points_spent($userId);
	return loyalty_for((float) $earned) + ['earned' => $earned, 'spent' => $spent, 'balance' => max(0, $earned - $spent)];
}
