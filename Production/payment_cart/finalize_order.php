<?php
require_once __DIR__ . '/../base.php';
require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

// This endpoint is called only after Stripe confirms card details on the client.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode((string) $rawBody, true);
if (!is_array($payload)) {
    $payload = [];
}

$paymentIntentId = trim((string) ($payload['payment_intent_id'] ?? ''));
if ($paymentIntentId === '') {
    echo json_encode(['success' => false, 'message' => 'payment_intent_id is required.']);
    exit;
}

$cartItems = $_SESSION['cart'] ?? [];
if (!is_array($cartItems) || count($cartItems) === 0) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty.']);
    exit;
}

$stripeConfig = require __DIR__ . '/../config/stripe.php';
if (!isset($stripeConfig['secret_key']) || trim((string) $stripeConfig['secret_key']) === '') {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Stripe secret key is missing.']);
    exit;
}

\Stripe\Stripe::setApiKey($stripeConfig['secret_key']);

try {
    // Re-check payment status server-side to prevent client-side spoofing.
    $intent = \Stripe\PaymentIntent::retrieve($paymentIntentId);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unable to verify payment intent.']);
    exit;
}

if (!isset($intent->status) || (string) $intent->status !== 'succeeded') {
    echo json_encode(['success' => false, 'message' => 'Payment is not successful yet.']);
    exit;
}

$subtotal = 0.0;
$restaurantIds = [];
foreach ($cartItems as $item) {
    $quantity = max(1, (int) ($item['quantity'] ?? 1));
    $unitAmount = (float) ($item['unitAmount'] ?? 0);
    $subtotal += $quantity * $unitAmount;

    $restaurantId = max(0, (int) ($item['restaurantId'] ?? 0));
    if ($restaurantId > 0) {
        // Track unique restaurants in cart; useful when deriving payment-level restaurant.
        $restaurantIds[$restaurantId] = true;
    }
}

$sst = round($subtotal * 0.06, 2);
$grandTotal = round($subtotal + $sst, 2);
$currency = strtoupper((string) ($intent->currency ?? 'MYR'));
$providerPaymentId = (string) $intent->id;
$paymentType = 'card';

if (isset($intent->payment_method_types) && is_array($intent->payment_method_types) && count($intent->payment_method_types) > 0) {
    $paymentType = (string) $intent->payment_method_types[0];
}

$restaurantId = null;
if (count($restaurantIds) === 1) {
    // Keep restaurant at payment header only when all items come from one restaurant.
    $ids = array_keys($restaurantIds);
    $restaurantId = (int) $ids[0];
}

$paidAt = date('Y-m-d H:i:s');
$transactionStarted = false;

try {
    // Idempotency guard: avoid creating duplicate DB rows for same provider payment id.
    $checkStmt = $conn->prepare('SELECT Payment_ID FROM Payment WHERE Provider_payment_id = ? LIMIT 1');
    if ($checkStmt === false) {
        throw new Exception('Failed to prepare duplicate check.');
    }

    $checkStmt->bind_param('s', $providerPaymentId);
    $checkStmt->execute();
    $existsResult = $checkStmt->get_result();

    if ($existsResult !== false && $existsResult->num_rows > 0) {
        $row = $existsResult->fetch_assoc();
        $paymentId = (int) $row['Payment_ID'];
        $checkStmt->close();

        $_SESSION['last_payment_id'] = $paymentId;
        $_SESSION['cart'] = [];

        echo json_encode(['success' => true, 'payment_id' => $paymentId, 'message' => 'Payment already finalized.']);
        exit;
    }

    $checkStmt->close();

    $conn->begin_transaction();
    $transactionStarted = true;

    // Write payment header first, then details in Payment_Item.
    $insertPaymentSql = 'INSERT INTO Payment (User_ID, Restaurant_ID, Payment_type, Payment_amount, Subtotal_amount, SST_amount, Currency, Payment_status, Provider, Provider_payment_id, Paid_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $paymentStmt = $conn->prepare($insertPaymentSql);

    if ($paymentStmt === false) {
        throw new Exception('Failed to prepare payment insert.');
    }

    $paymentStatus = 'SUCCEEDED';
    $provider = 'STRIPE';
    $restaurantIdForDb = $restaurantId !== null ? $restaurantId : null;

    $paymentStmt->bind_param(
        'iisdddsssss',
        $userId,
        $restaurantIdForDb,
        $paymentType,
        $grandTotal,
        $subtotal,
        $sst,
        $currency,
        $paymentStatus,
        $provider,
        $providerPaymentId,
        $paidAt
    );

    if (!$paymentStmt->execute()) {
        throw new Exception('Failed to insert payment.');
    }

    $paymentId = (int) $paymentStmt->insert_id;
    $paymentStmt->close();

    $insertItemSql = 'INSERT INTO Payment_Item (Payment_ID, Food_ID, Item_name, Item_type, Unit_amount, Quantity, Line_total, Sugar_level, Ice_level, Remark) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $itemStmt = $conn->prepare($insertItemSql);

    if ($itemStmt === false) {
        throw new Exception('Failed to prepare payment item insert.');
    }

    foreach ($cartItems as $item) {
        // Snapshot item info so Bills still works even if menu/price changes later.
        $foodId = max(0, (int) ($item['foodId'] ?? 0));
        $foodIdForDb = $foodId > 0 ? $foodId : null;

        $itemName = (string) ($item['itemName'] ?? '');
        $itemType = (string) ($item['itemType'] ?? '');
        $quantity = max(1, (int) ($item['quantity'] ?? 1));
        $unitAmount = round((float) ($item['unitAmount'] ?? 0), 2);
        $lineTotal = round($unitAmount * $quantity, 2);
        $sugar = (string) ($item['sugar'] ?? '');
        $ice = (string) ($item['ice'] ?? '');
        $remark = (string) ($item['remark'] ?? '');

        $itemStmt->bind_param(
            'iissdiddss',
            $paymentId,
            $foodIdForDb,
            $itemName,
            $itemType,
            $unitAmount,
            $quantity,
            $lineTotal,
            $sugar,
            $ice,
            $remark
        );

        if (!$itemStmt->execute()) {
            throw new Exception('Failed to insert payment item.');
        }
    }

    $itemStmt->close();
    $conn->commit();
    $transactionStarted = false;

    // Keep the latest payment id for success page and clear cart after successful commit.
    $_SESSION['last_payment_id'] = $paymentId;
    $_SESSION['cart'] = [];

    echo json_encode(['success' => true, 'payment_id' => $paymentId]);
} catch (\Throwable $e) {
    if ($transactionStarted) {
        // Any write failure should revert both header and item rows.
        $conn->rollback();
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to finalize order.']);
}
