<?php
/**
 * Cart (AJAX). Guests can build a cart; checkout needs an account.
 * POST action = get | add | qty | remove | clear | promo | promo_remove | vouchers
 */
require __DIR__ . '/../../app/bootstrap.php';
require __DIR__ . '/../../app/services/cart.php';

$action = api_start();
$user = current_user();
$uid = $user ? (int) $user['User_ID'] : null;

try {
	switch ($action) {
		case 'get':
			break;
		case 'add':
			cart_add((int) input('food_id'), (int) input('quantity', '1'), input('sugar'), input('ice'), input('remark'));
			break;
		case 'qty':
			cart_set_qty(input('cart_id'), (int) input('quantity'));
			break;
		case 'remove':
			cart_set_qty(input('cart_id'), 0);
			break;
		case 'clear':
			cart_clear();
			break;
		case 'promo':
			$code = strtoupper(input('code'));
			if ($code === '') json_out(false, ['fields' => ['code' => 'Enter a code.']], 'Enter a code.', 422);
			$sum = cart_summary($uid);
			$check = promo_check($code, $sum['subtotal'], $uid);
			if (!is_array($check)) json_out(false, ['fields' => ['code' => $check]], $check, 422);
			$_SESSION['promo_code'] = $check['Code'];
			break;
		case 'promo_remove':
			unset($_SESSION['promo_code']);
			break;
		case 'vouchers':
			// Checkout voucher cards (signed-in customers only).
			if (!$uid) json_out(false, null, 'Sign in to see your vouchers.', 401);
			$sum = cart_summary($uid);
			json_out(true, ['vouchers' => promo_available($uid, $sum['subtotal']), 'applied' => $sum['promo']['code'] ?? null, 'subtotal' => $sum['subtotal']]);
		default:
			json_out(false, null, 'Unknown action.', 400);
	}
} catch (InvalidArgumentException $e) {
	json_out(false, null, $e->getMessage(), 422);
}

json_out(true, cart_summary($uid));
