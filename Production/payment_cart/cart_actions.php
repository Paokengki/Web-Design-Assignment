<?php
// jian how see this file for reference only, not to be used in the project
require_once __DIR__ . '/../base.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

function generateCartId() {
    return 'c_' . bin2hex(random_bytes(6));
}

function ensureCartIds() {
    foreach ($_SESSION['cart'] as $index => $item) {
        if (!isset($_SESSION['cart'][$index]['cartId']) || trim((string) $_SESSION['cart'][$index]['cartId']) === '') {
            $_SESSION['cart'][$index]['cartId'] = generateCartId();
        }
    }
}

function findCartItemIndexById($cartId) {
    foreach ($_SESSION['cart'] as $index => $item) {
        if ((string) ($item['cartId'] ?? '') === $cartId) {
            return $index;
        }
    }

    return -1;
}

function calculateCartSummary($items) {
    $subtotal = 0.0;
    $normalized = [];

    foreach ($items as $item) {
        $cartId = (string) ($item['cartId'] ?? '');
        if ($cartId === '') {
            $cartId = generateCartId();
        }

        $quantity = max(1, (int) ($item['quantity'] ?? 1));
        $unitAmount = (float) ($item['unitAmount'] ?? 0);
        $lineTotal = $quantity * $unitAmount;
        $subtotal += $lineTotal;

        $normalized[] = [
            'cartId' => $cartId,
            'itemName' => (string) ($item['itemName'] ?? ''),
            'itemType' => (string) ($item['itemType'] ?? ''),
            'quantity' => $quantity,
            'unitAmount' => round($unitAmount, 2),
            'lineTotal' => round($lineTotal, 2),
            'sugar' => (string) ($item['sugar'] ?? ''),
            'ice' => (string) ($item['ice'] ?? ''),
            'remark' => (string) ($item['remark'] ?? '')
        ];
    }

    $sst = round($subtotal * 0.06, 2);
    $grandTotal = round($subtotal + $sst, 2);

    return [
        'success' => true,
        'items' => $normalized,
        'subtotal' => round($subtotal, 2),
        'sst' => $sst,
        'grandTotal' => $grandTotal
    ];
}

$action = (string) ($_REQUEST['action'] ?? 'get');
ensureCartIds();

if ($action === 'add') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        exit;
    }

    $itemName = trim((string) ($_POST['item_name'] ?? ''));
    $itemType = trim((string) ($_POST['item_type'] ?? ''));
    $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
    $unitAmount = (float) ($_POST['unit_amount'] ?? 0);
    $sugar = trim((string) ($_POST['sugar'] ?? ''));
    $ice = trim((string) ($_POST['ice'] ?? ''));
    $remark = trim((string) ($_POST['remark'] ?? ''));

    if ($itemName === '') {
        echo json_encode(['success' => false, 'message' => 'Item name is required.']);
        exit;
    }

    $_SESSION['cart'][] = [
        'cartId' => generateCartId(),
        'itemName' => $itemName,
        'itemType' => $itemType,
        'quantity' => $quantity,
        'unitAmount' => $unitAmount,
        'sugar' => $sugar,
        'ice' => $ice,
        'remark' => $remark
    ];

    echo json_encode(calculateCartSummary($_SESSION['cart']));
    exit;
}

if ($action === 'update') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        exit;
    }

    $cartId = trim((string) ($_POST['cart_id'] ?? ''));
    $quantity = max(1, (int) ($_POST['quantity'] ?? 1));

    if ($cartId === '') {
        echo json_encode(['success' => false, 'message' => 'cart_id is required.']);
        exit;
    }

    $index = findCartItemIndexById($cartId);
    if ($index < 0) {
        echo json_encode(['success' => false, 'message' => 'Cart item not found.']);
        exit;
    }

    $_SESSION['cart'][$index]['quantity'] = $quantity;

    echo json_encode(calculateCartSummary($_SESSION['cart']));
    exit;
}

if ($action === 'remove') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        exit;
    }

    $cartId = trim((string) ($_POST['cart_id'] ?? ''));
    if ($cartId === '') {
        echo json_encode(['success' => false, 'message' => 'cart_id is required.']);
        exit;
    }

    $index = findCartItemIndexById($cartId);
    if ($index < 0) {
        echo json_encode(['success' => false, 'message' => 'Cart item not found.']);
        exit;
    }

    array_splice($_SESSION['cart'], $index, 1);

    echo json_encode(calculateCartSummary($_SESSION['cart']));
    exit;
}

if ($action === 'clear') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        exit;
    }

    $_SESSION['cart'] = [];
    echo json_encode(calculateCartSummary($_SESSION['cart']));
    exit;
}

if ($action === 'get') {
    echo json_encode(calculateCartSummary($_SESSION['cart']));
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action.']);
