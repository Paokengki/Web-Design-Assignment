<?php
/**
 * Stripe Checkout (hosted payment page). Test or live depends on the keys in config/stripe.php.
 *
 * Flow
 *  1. customer/checkout.php → POST api/shop/checkout.php (action=session)
 *     payment_create_session() prices the cart HERE, creates a Checkout Session, returns its URL.
 *  2. The browser goes to Stripe's page; the customer pays (test card 4242 4242 4242 4242).
 *  3. Stripe sends them to customer/checkout_return.php?session_id=cs_…
 *     payment_finalize_session() re-reads the session from Stripe (never trusts the URL),
 *     checks it is paid and belongs to this customer, then writes Payment + Payment_Item
 *     (+ Promotion_Redemption) in one transaction. Safe to run twice for the same payment.
 *
 * Requires: composer install (stripe/stripe-php) and config/stripe.php returning
 *   ['secret_key' => 'sk_test_…', 'publishable_key' => 'pk_test_…']
 */
require_once __DIR__ . '/cart.php';

function stripe_config(): ?array
{
	static $cfg = false;
	if ($cfg === false) {
		$root = dirname(__DIR__, 2);
		$file = $root . '/config/stripe.php';
		$cfg = null;
		if (is_file($file) && is_file($root . '/vendor/autoload.php')) {
			$c = require $file;
			if (!empty($c['secret_key']) && !empty($c['publishable_key'])) {
				require_once $root . '/vendor/autoload.php';
				\Stripe\Stripe::setApiKey($c['secret_key']);
				$cfg = $c;
			}
		}
	}
	return $cfg;
}

function stripe_ready(): bool
{
	return stripe_config() !== null;
}

function absolute_url(string $path): string
{
	$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
	return $scheme . '://' . $_SERVER['HTTP_HOST'] . url($path);
}

/** Step 1: create the Checkout Session for the current cart and return Stripe's payment page URL. */
function payment_create_session(array $user): string
{
	if (!stripe_ready()) throw new RuntimeException('Payments are not configured on this server.');
	$uid = (int) $user['User_ID'];
	$sum = cart_summary($uid);
	if (!$sum['items']) throw new InvalidArgumentException('Your cart is empty.');
	if ($sum['total'] < 2) throw new InvalidArgumentException('Card payments need a total of at least RM2.00.');
	if (!q_one('SELECT 1 FROM User_Address WHERE User_ID = ?', [$uid])) throw new InvalidArgumentException('Add a delivery address before paying.');

	$lines = [];
	foreach ($sum['items'] as $it) {
		$opts = array_filter([$it['sugar'] ? 'Sugar ' . strtolower($it['sugar']) : '', $it['ice'] ? 'Ice ' . strtolower($it['ice']) : '', $it['remark'] ? '"' . $it['remark'] . '"' : '']);
		$lines[] = [
			'quantity'   => (int) $it['quantity'],
			'price_data' => [
				'currency'     => 'myr',
				'unit_amount'  => (int) round($it['unitAmount'] * 100),
				'product_data' => ['name' => $it['itemName'], 'description' => $it['restaurantName'] . ($opts ? ' · ' . implode(', ', $opts) : '')],
			],
		];
	}
	if ($sum['sst'] > 0) {
		$lines[] = ['quantity' => 1, 'price_data' => ['currency' => 'myr', 'unit_amount' => (int) round($sum['sst'] * 100), 'product_data' => ['name' => 'SST (6%)']]];
	}

	$params = [
		'mode'                => 'payment',
		'payment_method_types' => ['card'],
		'line_items'          => $lines,
		'customer_email'      => $user['Email'] ?: null,
		'client_reference_id' => (string) $uid,
		'locale'              => 'en',
		'metadata'            => ['user_id' => (string) $uid, 'promo' => $sum['promo']['code'] ?? ''],
		'payment_intent_data' => ['metadata' => ['user_id' => (string) $uid, 'source' => 'cafedash']],
		'success_url'         => absolute_url('customer/checkout_return.php') . '?session_id={CHECKOUT_SESSION_ID}',
		'cancel_url'          => absolute_url('customer/checkout.php') . '?cancelled=1',
	];
	if ($sum['discount'] > 0) {
		// Stripe has no negative line items, so the voucher becomes a one-off coupon for the exact amount.
		$coupon = \Stripe\Coupon::create([
			'amount_off' => (int) round($sum['discount'] * 100), 'currency' => 'myr', 'duration' => 'once',
			'max_redemptions' => 1, 'name' => $sum['promo']['code'] . ' · ' . rtrim(rtrim(number_format($sum['promo']['percent'], 2), '0'), '.') . '% off',
		]);
		$params['discounts'] = [['coupon' => $coupon->id]];
	}
	if (!$params['customer_email']) unset($params['customer_email']);

	$session = \Stripe\Checkout\Session::create($params);
	// Snapshot what is being paid for, so the order matches the charge even if the cart changes meanwhile.
	$_SESSION['checkout_session'] = ['id' => $session->id, 'summary' => $sum];
	return $session->url;
}

/** Step 3: verify the Checkout Session with Stripe and record the order. Returns the Payment_ID. */
function payment_finalize_session(array $user, string $sessionId): int
{
	if (!stripe_ready()) throw new RuntimeException('Payments are not configured on this server.');
	if (!preg_match('/^cs_(test|live)_[A-Za-z0-9]+$/', $sessionId)) throw new InvalidArgumentException('Invalid payment reference.');
	$uid = (int) $user['User_ID'];

	$session = \Stripe\Checkout\Session::retrieve($sessionId);
	if ((string) ($session->metadata['user_id'] ?? '') !== (string) $uid) throw new InvalidArgumentException('This payment belongs to another account.');
	if ($session->payment_status !== 'paid') throw new InvalidArgumentException('Stripe has not confirmed this payment yet. Refresh in a moment.');

	$intentId = is_string($session->payment_intent) ? $session->payment_intent : $session->payment_intent->id;
	$existing = q_one('SELECT Payment_ID FROM Payment WHERE Provider_payment_id = ?', [$intentId]);
	if ($existing) {
		cart_clear();
		return (int) $existing['Payment_ID'];
	}

	$snap = $_SESSION['checkout_session'] ?? null;
	$sum = ($snap && $snap['id'] === $sessionId) ? $snap['summary'] : cart_summary($uid);
	if (!$sum['items']) throw new InvalidArgumentException('We received your payment but lost the cart. Contact us with reference ' . $intentId . '.');
	if ((int) $session->amount_total !== (int) round($sum['total'] * 100)) {
		error_log("CafeDash: amount mismatch for $sessionId — charged {$session->amount_total}, order {$sum['total']}");
	}

	$paymentId = record_paid_order($uid, $sum, $intentId, (int) $session->amount_total, (string) $session->currency);
	unset($_SESSION['checkout_session']);
	$_SESSION['last_payment_id'] = $paymentId;
	cart_clear();
	return $paymentId;
}

/** Write Payment + Payment_Item (+ voucher redemption) in one transaction. */
function record_paid_order(int $uid, array $sum, string $intentId, int $chargedSen, string $currency): int
{
	$cafes = array_unique(array_column($sum['items'], 'restaurantId'));
	$restaurantId = count($cafes) === 1 ? (int) reset($cafes) : null;

	db()->begin_transaction();
	try {
		q('INSERT INTO Payment (User_ID, Restaurant_ID, Payment_type, Payment_amount, Subtotal_amount, SST_amount, Discount_amount, Promo_ID, Currency, Payment_status, Provider, Provider_payment_id, Paid_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())', [
			$uid, $restaurantId, 'card', round($chargedSen / 100, 2), $sum['subtotal'], $sum['sst'], $sum['discount'], $sum['promo']['id'] ?? null,
			strtoupper($currency), 'SUCCEEDED', 'STRIPE', $intentId,
		]);
		$paymentId = (int) db()->insert_id;

		foreach ($sum['items'] as $it) {
			q('INSERT INTO Payment_Item (Payment_ID, Food_ID, Item_name, Item_type, Unit_amount, Quantity, Line_total, Sugar_level, Ice_level, Remark)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
				$paymentId, $it['foodId'] ?: null, $it['itemName'], $it['itemType'], (float) $it['unitAmount'], (int) $it['quantity'], (float) $it['lineTotal'],
				$it['sugar'], $it['ice'], $it['remark'],
			]);
		}

		if ($sum['promo']) {
			q('INSERT INTO Promotion_Redemption (Promo_ID, User_ID, Payment_ID, Discount_amount) VALUES (?, ?, ?, ?)', [$sum['promo']['id'], $uid, $paymentId, $sum['discount']]);
			q('UPDATE Promotion SET Redemption_count = Redemption_count + 1 WHERE Promo_ID = ?', [$sum['promo']['id']]);
		}
		db()->commit();
		return $paymentId;
	} catch (Throwable $e) {
		db()->rollback();
		throw $e;
	}
}
