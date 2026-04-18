<?php
// Keep this endpoint JSON-only even though it loads shared bootstrap code.
ob_start();

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../base.php';
require_once __DIR__ . '/../vendor/autoload.php';

$stripeConfig = require __DIR__ . '/../config/stripe.php';
if (isset($stripeConfig['secret_key'])) {
    \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);
}

if (ob_get_length()) {
    ob_clean();
}
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    // Recompute the charge server-side from the cart session.
    $items = $_SESSION['cart'] ?? [];
    if (empty($items)) {
        throw new Exception('Cart is empty');
    }

    $subtotal = 0.0;
    foreach ($items as $item) {
        $quantity   = max(1, (int) ($item['quantity'] ?? 1));
        $unitAmount = (float) ($item['unitAmount'] ?? 0);
        $subtotal  += $quantity * $unitAmount;
    }

    $sst         = round($subtotal * 0.06, 2);
    $grandTotal  = round($subtotal + $sst, 2);
    $amountInSen = (int) round($grandTotal * 100);

    $paymentIntent = \Stripe\PaymentIntent::create([
        'amount'   => $amountInSen,
        'currency' => 'myr',
        'metadata' => ['source' => 'cafe_dash'],
    ]);

    echo json_encode(['clientSecret' => $paymentIntent->client_secret]);

} catch (\Stripe\Exception\ApiErrorException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Stripe API: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

exit;