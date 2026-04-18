<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '只支持POST请求']);
    exit;
}

// Get POST data
$user_id = $_SESSION['user_id'];
$current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
$new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
$confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

// Validate required fields
if (empty($current_password)) {
    echo json_encode(['success' => false, 'message' => '请输入当前密码']);
    exit;
}

if (empty($new_password)) {
    echo json_encode(['success' => false, 'message' => '请输入新密码']);
    exit;
}

if (empty($confirm_password)) {
    echo json_encode(['success' => false, 'message' => '请确认新密码']);
    exit;
}

// Check if new password and confirm password match
if ($new_password !== $confirm_password) {
    echo json_encode(['success' => false, 'message' => '新密码与确认密码不一致']);
    exit;
}

// Check password length
if (strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'message' => '新密码长度至少需要6个字符']);
    exit;
}

// Check if new password is same as current password
if ($current_password === $new_password) {
    echo json_encode(['success' => false, 'message' => '新密码不能与当前密码相同']);
    exit;
}

// Database connection
$conn = new mysqli("localhost", "root", "", "cafedash_db");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => '数据库连接失败']);
    exit;
}

// Get current password from database
$stmt = $conn->prepare("SELECT Password FROM User WHERE User_ID = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => '数据库错误: ' . $conn->error]);
    $conn->close();
    exit;
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => '用户不存在']);
    $stmt->close();
    $conn->close();
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// Verify current password (support both hashed and plain text for backward compatibility)
$password_verified = false;
if (password_verify($current_password, $user['Password'])) {
    $password_verified = true;
} elseif ($current_password === $user['Password']) {
    // Fallback for plain text passwords
    $password_verified = true;
}

if (!$password_verified) {
    echo json_encode(['success' => false, 'message' => '当前密码错误']);
    $conn->close();
    exit;
}

// Hash the new password
$hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);

// Update password in database (hashed)
$update_stmt = $conn->prepare("UPDATE User SET Password = ? WHERE User_ID = ?");
if (!$update_stmt) {
    echo json_encode(['success' => false, 'message' => '数据库错误: ' . $conn->error]);
    $conn->close();
    exit;
}

$update_stmt->bind_param("si", $hashed_new_password, $user_id);

if ($update_stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => '密码已成功更新'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => '更新失败: ' . $update_stmt->error]);
}

$update_stmt->close();
$conn->close();
?>
