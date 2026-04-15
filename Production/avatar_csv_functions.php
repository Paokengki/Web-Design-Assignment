<?php
/**
 * 从CSV文件获取用户的头像路径
 */
function getAvatarFromCSV($user_id) {
    $csv_file = 'data/avatar_mapping.csv';
    
    if (!file_exists($csv_file)) {
        return null;
    }
    
    $fh = fopen($csv_file, 'r');
    while (($row = fgetcsv($fh)) !== false) {
        if (!empty($row[0]) && $row[0] == $user_id) {
            fclose($fh);
            return isset($row[1]) ? $row[1] : null;
        }
    }
    fclose($fh);
    
    return null;
}

/**
 * 获取所有CSV中存储的头像信息
 */
function getAllAvatars() {
    $avatars = [];
    $csv_file = 'data/avatar_mapping.csv';
    
    if (!file_exists($csv_file)) {
        return $avatars;
    }
    
    $fh = fopen($csv_file, 'r');
    while (($row = fgetcsv($fh)) !== false) {
        if (!empty($row[0]) && $row[0] != 'User_ID') {
            $avatars[$row[0]] = $row[1] ?? null;
        }
    }
    fclose($fh);
    
    return $avatars;
}

/**
 * 删除CSV中的用户头像记录
 */
function deleteAvatarFromCSV($user_id) {
    $csv_file = 'data/avatar_mapping.csv';
    
    if (!file_exists($csv_file)) {
        return false;
    }
    
    $data = [];
    $fh = fopen($csv_file, 'r');
    while (($row = fgetcsv($fh)) !== false) {
        if (!empty($row[0]) && $row[0] != 'User_ID' && $row[0] != $user_id) {
            $data[$row[0]] = $row[1];
        }
    }
    fclose($fh);
    
    $fh = fopen($csv_file, 'w');
    fputcsv($fh, ['User_ID', 'Avatar_Path']);
    foreach ($data as $uid => $path) {
        fputcsv($fh, [$uid, $path]);
    }
    fclose($fh);
    
    return true;
}
?>
