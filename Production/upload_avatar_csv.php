<?php
session_start();
header('Content-Type: application/json');

// 调试日志
error_log('=== Upload Avatar CSV Debug ===');
error_log('Session user_id: ' . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log('Request method: ' . $_SERVER['REQUEST_METHOD']);
error_log('Files: ' . json_encode(array_keys($_FILES)));

if (!isset($_SESSION['user_id'])) {
    error_log('Error: User not logged in');
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log('Error: Not a POST request');
    echo json_encode(['success' => false, 'message' => '只支持POST请求']);
    exit;
}

if (!isset($_FILES['profileImage'])) {
    error_log('Error: No profileImage file');
    echo json_encode(['success' => false, 'message' => '没有上传文件']);
    exit;
}

$file = $_FILES['profileImage'];
$user_id = $_SESSION['user_id'];

// 检查上传错误
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => '文件上传失败']);
    exit;
}

// 简单的文件类型检查
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
    echo json_encode(['success' => false, 'message' => '不支持的文件格式']);
    exit;
}

// 检查文件大小
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => '文件太大（超过5MB）']);
    exit;
}

// 上传目录
$upload_dir = 'material/images/uploads/';

// 创建目录
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// 生成文件名
$filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
$filepath = $upload_dir . $filename;

// 保存文件
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode(['success' => false, 'message' => '文件保存失败']);
    exit;
}

// 验证文件是否真的存在
if (!file_exists($filepath)) {
    echo json_encode(['success' => false, 'message' => '文件保存后找不到']);
    exit;
}

// 保存到CSV文件
$csv_file = 'data/avatar_mapping.csv';
$csv_dir = dirname($csv_file);

// 创建CSV目录
if (!is_dir($csv_dir)) {
    mkdir($csv_dir, 0755, true);
}

// 读取现有的CSV数据
$data = [];
if (file_exists($csv_file)) {
    $fh = fopen($csv_file, 'r');
    while (($row = fgetcsv($fh)) !== false) {
        if (!empty($row[0]) && $row[0] != 'User_ID') { // 跳过表头
            $data[$row[0]] = $row[1]; // 用户ID => 文件路径
        }
    }
    fclose($fh);
}

// 更新当前用户的头像
$data[$user_id] = $filepath;

// 写入CSV文件
$fh = fopen($csv_file, 'w');
fputcsv($fh, ['User_ID', 'Avatar_Path']);
foreach ($data as $uid => $path) {
    fputcsv($fh, [$uid, $path]);
}
fclose($fh);

echo json_encode([
    'success' => true,
    'message' => '上传成功',
    'filepath' => $filepath,
    'filename' => $filename,
    'csv_saved' => true
]);
?>
