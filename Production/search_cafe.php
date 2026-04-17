<?php
require_once 'base.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

$keyword = trim((string) ($_GET['q'] ?? ''));
if ($keyword === '') {
    echo json_encode([
        'success' => false,
        'found' => false,
        'message' => 'Search keyword is required.'
    ]);
    exit;
}

$exactSql = 'SELECT Restaurant_ID, Name FROM Restaurant WHERE LOWER(Name) = LOWER(?) LIMIT 1';
$stmt = $conn->prepare($exactSql);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to prepare exact search query.'
    ]);
    exit;
}

$stmt->bind_param('s', $keyword);
$stmt->execute();
$result = $stmt->get_result();

if ($result !== false && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'found' => true,
        'cafeId' => (int) $row['Restaurant_ID'],
        'cafeName' => $row['Name']
    ]);
    exit;
}

$stmt->close();

$likeSql = 'SELECT Restaurant_ID, Name FROM Restaurant WHERE Name LIKE ? ORDER BY Name ASC LIMIT 1';
$stmt = $conn->prepare($likeSql);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to prepare partial search query.'
    ]);
    exit;
}

$partialKeyword = '%' . $keyword . '%';
$stmt->bind_param('s', $partialKeyword);
$stmt->execute();
$result = $stmt->get_result();

if ($result !== false && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'found' => true,
        'cafeId' => (int) $row['Restaurant_ID'],
        'cafeName' => $row['Name']
    ]);
    exit;
}

$stmt->close();

echo json_encode([
    'success' => true,
    'found' => false,
    'message' => 'No cafe found.'
]);
