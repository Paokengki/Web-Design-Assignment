<?php
function avatarCsvPath() {
    return __DIR__ . '/../data/avatar_mapping.csv';
}

function loadAvatarMap() {
    $csv_file = avatarCsvPath();
    $avatars = [];

    if (!file_exists($csv_file)) {
        return $avatars;
    }

    $fh = fopen($csv_file, 'r');
    if ($fh === false) {
        return $avatars;
    }

    while (($row = fgetcsv($fh)) !== false) {
        if (!empty($row[0]) && $row[0] !== 'User_ID') {
            $avatars[$row[0]] = $row[1] ?? null;
        }
    }

    fclose($fh);
    return $avatars;
}

function saveAvatarMap(array $avatars) {
    $csv_file = avatarCsvPath();
    $csv_dir = dirname($csv_file);

    if (!is_dir($csv_dir) && !mkdir($csv_dir, 0755, true) && !is_dir($csv_dir)) {
        return false;
    }

    $fh = fopen($csv_file, 'w');
    if ($fh === false) {
        return false;
    }

    fputcsv($fh, ['User_ID', 'Avatar_Path']);
    foreach ($avatars as $uid => $path) {
        fputcsv($fh, [$uid, $path]);
    }

    fclose($fh);
    return true;
}

// Return the stored avatar path for one user, if it exists.
function getAvatarFromCSV($user_id) {
    $avatars = loadAvatarMap();
    return $avatars[$user_id] ?? null;
}

// Return all stored avatar mappings as user_id => path.
function getAllAvatars() {
    return loadAvatarMap();
}

// Remove one user from the CSV mapping file.
function deleteAvatarFromCSV($user_id) {
    $avatars = loadAvatarMap();

    if (!array_key_exists($user_id, $avatars)) {
        return false;
    }

    unset($avatars[$user_id]);
    return saveAvatarMap($avatars);
}
