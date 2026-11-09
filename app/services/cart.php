<?php
/**
 * Session cart. Uses the same $_SESSION['cart'] shape as the old pages, so both stay in sync.
 * Prices are ALWAYS re-read from the Food table; the browser only sends food id, quantity and options.
 */
require_once __DIR__ . '/images.php';
require_once __DIR__ . '/promo.php';

const SST_RATE = 0.06;
const MAX_QTY = 20;
const DRINK_OPTIONS = ['Less', 'Normal', 'More'];

function is_drink_type(string $type): bool
{
	return (bool) preg_match('/drink|beverage|coffee|tea|latte|juice/i', $type);
}

function &cart_items(): array
{
	if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];
	return $_SESSION['cart'];
}

function cart_add(int $foodId, int $qty, string $sugar, string $ice, string $remark): void
{
	$food = q_one('SELECT f.Food_ID, f.Name, f.Food_type, f.amount, r.Restaurant_ID, r.Name AS cafe
		FROM Food f JOIN Restaurant r ON r.Restaurant_ID = f.Restaurant_ID WHERE f.Food_ID = ?', [$foodId]);
	if (!$food) throw new InvalidArgumentException('That item is no longer on the menu.');

	$drink = is_drink_type((string) $food['Food_type']);
	$sugar = $drink && in_array($sugar, DRINK_OPTIONS, true) ? $sugar : ($drink ? 'Normal' : '');
	$ice = $drink && in_array($ice, DRINK_OPTIONS, true) ? $ice : ($drink ? 'Normal' : '');
	$remark = mb_substr(trim($remark), 0, 200);
	$qty = max(1, min(MAX_QTY, $qty));

	$items = &cart_items();
	// Same dish with the same options → just increase the quantity.
	foreach ($items as &$it) {
		if ((int) ($it['foodId'] ?? 0) === $foodId && ($it['sugar'] ?? '') === $sugar && ($it['ice'] ?? '') === $ice && ($it['remark'] ?? '') === $remark) {
			$it['quantity'] = min(MAX_QTY, (int) $it['quantity'] + $qty);
			return;
		}
	}
	unset($it);
	$items[] = [
		'cartId'         => 'c_' . bin2hex(random_bytes(6)),
		'foodId'         => (int) $food['Food_ID'],
		'restaurantId'   => (int) $food['Restaurant_ID'],
		'restaurantName' => $food['cafe'],
		'itemName'       => $food['Name'],
		'itemType'       => (string) $food['Food_type'],
		'quantity'       => $qty,
		'unitAmount'     => (float) $food['amount'],
		'sugar'          => $sugar,
		'ice'            => $ice,
		'remark'         => $remark,
	];
}

function cart_set_qty(string $cartId, int $qty): void
{
	$items = &cart_items();
	foreach ($items as $i => &$it) {
		if (($it['cartId'] ?? '') === $cartId) {
			if ($qty <= 0) array_splice($items, $i, 1);
			else $it['quantity'] = min(MAX_QTY, $qty);
			return;
		}
	}
	throw new InvalidArgumentException('That item is no longer in your cart.');
}

function cart_clear(): void
{
	$_SESSION['cart'] = [];
	unset($_SESSION['promo_code']);
}

/**
 * Full priced cart: re-prices from the DB, drops dishes that were removed from the menu,
 * applies the session promo code if still valid.
 */
function cart_summary(?int $userId = null): array
{
	$items = &cart_items();
	$ids = array_values(array_unique(array_map(fn($i) => (int) ($i['foodId'] ?? 0), $items)));
	$prices = [];
	if ($ids) {
		$marks = implode(',', array_fill(0, count($ids), '?'));
		foreach (q_all("SELECT Food_ID, amount, Name FROM Food WHERE Food_ID IN ($marks)", $ids) as $f) $prices[(int) $f['Food_ID']] = $f;
	}

	$lines = [];
	$subtotal = 0.0;
	$count = 0;
	foreach ($items as $k => &$it) {
		$fid = (int) ($it['foodId'] ?? 0);
		if (!isset($prices[$fid])) { unset($items[$k]); continue; } // removed from menu
		$it['unitAmount'] = (float) $prices[$fid]['amount'];
		$qty = max(1, min(MAX_QTY, (int) $it['quantity']));
		$line = round($qty * $it['unitAmount'], 2);
		$subtotal += $line;
		$count += $qty;
		$lines[] = [
			'cartId' => $it['cartId'], 'foodId' => $fid, 'restaurantId' => (int) $it['restaurantId'],
			'restaurantName' => $it['restaurantName'], 'itemName' => $it['itemName'], 'itemType' => $it['itemType'],
			'quantity' => $qty, 'unitAmount' => $it['unitAmount'], 'lineTotal' => $line,
			'sugar' => $it['sugar'] ?? '', 'ice' => $it['ice'] ?? '', 'remark' => $it['remark'] ?? '',
			'image' => food_image_url($it['restaurantName'], $it['itemName']),
		];
	}
	unset($it);
	$items = array_values($items);
	$subtotal = round($subtotal, 2);

	// promo code (if any) is re-validated every time the cart is priced
	$promo = null;
	$discount = 0.0;
	$promoError = null;
	if (!empty($_SESSION['promo_code']) && $lines) {
		$check = promo_check($_SESSION['promo_code'], $subtotal, $userId);
		if (is_array($check)) {
			$promo = ['id' => (int) $check['Promo_ID'], 'code' => $check['Code'], 'percent' => (float) $check['Discount_percent'], 'title' => $check['Title']];
			$discount = round($subtotal * $promo['percent'] / 100, 2);
		} else {
			$promoError = $check;
			unset($_SESSION['promo_code']);
		}
	}

	$taxable = max(0, $subtotal - $discount);
	$sst = round($taxable * SST_RATE, 2);
	return [
		'items' => $lines, 'count' => $count,
		'subtotal' => $subtotal, 'discount' => $discount, 'sst' => $sst, 'total' => round($taxable + $sst, 2),
		'promo' => $promo, 'promo_error' => $promoError,
	];
}
