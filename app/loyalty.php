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
