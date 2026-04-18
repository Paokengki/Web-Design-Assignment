<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests are supported']);
    exit;
}

// Get POST data
$user_id = $_SESSION['user_id'];
$full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$address = isset($_POST['address']) ? trim($_POST['address']) : '';

// Validate required fields
if (empty($full_name)) {
    echo json_encode(['success' => false, 'message' => 'Name cannot be empty']);
    exit;
}

// Database connection
$conn = new mysqli("localhost", "root", "", "cafedash_db");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Update user information
$stmt = $conn->prepare("UPDATE User SET User_Name = ?, Email = ?, Contain_number = ?, Address = ? WHERE User_ID = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    $conn->close();
    exit;
}

$stmt->bind_param("ssssi", $full_name, $email, $phone, $address, $user_id);

if ($stmt->execute()) {
    // Update session with new name
    $_SESSION['User_name'] = $full_name;
    
    echo json_encode([
        'success' => true,
        'message' => 'Personal information saved successfully',
        'data' => [
            'full_name' => $full_name,
            'email' => $email,
            'phone' => $phone,
            'address' => $address
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Save failed: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
