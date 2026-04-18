<?php
require_once __DIR__ . '/../../profile/avatar_csv_functions.php';

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

if (!isset($_FILES['profileImage'])) {
    echo json_encode(['success' => false, 'message' => '没有上传文件']);
    exit;
}

$file = $_FILES['profileImage'];
$user_id = $_SESSION['user_id'];

// Validate the upload before saving anything to disk.
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => '文件上传失败']);
    exit;
}

// Only allow common image formats.
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
    echo json_encode(['success' => false, 'message' => '不支持的文件格式']);
    exit;
}

// Keep avatar uploads small.
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => '文件太大（超过5MB）']);
    exit;
}

// Store the file inside the project uploads folder.
$project_root = dirname(__DIR__, 2);
$upload_dir = $project_root . '/material/images/uploads/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
$filepath = $upload_dir . $filename;
$webpath = '../material/images/uploads/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode(['success' => false, 'message' => '文件保存失败']);
    exit;
}

if (!file_exists($filepath)) {
    echo json_encode(['success' => false, 'message' => '文件保存后找不到']);
    exit;
}

// Update the CSV mapping with the new avatar path.
$avatars = getAllAvatars();
$avatars[$user_id] = $webpath;
if (!saveAvatarMap($avatars)) {
    echo json_encode(['success' => false, 'message' => '头像记录保存失败']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => '上传成功',
    'filepath' => $webpath,
    'filename' => $filename,
    'csv_saved' => true
]);
?>
