<?php
require_once __DIR__ . '/../../base.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$limit = (int) ($_GET['limit'] ?? 10);
if ($limit <= 0) {
    $limit = 10;
}
if ($limit > 100) {
    $limit = 100;
}

// Rank restaurants by successful transaction count, then break ties by total sales.
$sql = "
    SELECT
        r.Restaurant_ID,
        r.Name,
        COUNT(p.Payment_ID) AS transaction_count,
        COALESCE(SUM(p.Payment_amount), 0) AS total_sales
    FROM Restaurant r
    LEFT JOIN Payment p
        ON p.Restaurant_ID = r.Restaurant_ID
        AND p.Payment_status = 'SUCCEEDED'
    GROUP BY r.Restaurant_ID, r.Name
    ORDER BY transaction_count DESC, total_sales DESC, r.Name ASC
    LIMIT ?
";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare ranking query.']);
    exit;
}

$stmt->bind_param('i', $limit);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
if ($result !== false) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'restaurantId' => (int) $row['Restaurant_ID'],
            'restaurantName' => (string) $row['Name'],
            'transactionCount' => (int) $row['transaction_count'],
            'totalSales' => round((float) $row['total_sales'], 2)
        ];
    }
}

$stmt->close();

echo json_encode([
    'success' => true,
    'limit' => $limit,
    'ranking' => $rows
]);
