<?php
/**
 * Loyalty rewards (AJAX). Signed-in customers only.
 * POST action = list              → { points, rewards[], vouchers[] }
 *      action = redeem, Promo_ID  → same payload after spending the points, plus `redeemed`
 *
 * All rules and the points deduction live in reward_redeem() (app/services/promo.php),
 * inside one database transaction.
 */
require __DIR__ . '/../../app/bootstrap.php';
require __DIR__ . '/../../app/services/promo.php';

$action = api_start();
$user = require_customer();
$uid = (int) $user['User_ID'];

function rewards_payload(int $uid): array
{
	return ['points' => loyalty_summary($uid), 'rewards' => rewards_catalog($uid), 'vouchers' => rewards_owned($uid)];
}

switch ($action) {
	case 'list':
		json_out(true, rewards_payload($uid));

	case 'redeem':
		try {
			$done = reward_redeem($uid, (int) input('Promo_ID'));
		} catch (InvalidArgumentException $e) {
			json_out(false, rewards_payload($uid), $e->getMessage(), 422); // fresh data so the page can correct itself
		}
		json_out(true, rewards_payload($uid) + ['redeemed' => $done]);

	default:
		json_out(false, null, 'Unknown action.', 400);
}
