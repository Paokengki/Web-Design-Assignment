<?php
/**
 * Customer account (AJAX).
 * POST action = update_contact | change_password | deactivate
 */
require __DIR__ . '/../../app/bootstrap.php';

$action = api_start();
$user = require_customer();
$uid = (int) $user['User_ID'];

switch ($action) {
	case 'update_contact':
		$name = input('User_Name');
		$email = input('Email');
		$phone = input('Contain_number');
		$errors = [];
		if (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $name)) $errors['User_Name'] = '3–30 letters, numbers, dots or underscores.';
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['Email'] = 'Enter a valid email address.';
		if ($phone !== '' && !preg_match('/^[0-9+\-\s]{9,20}$/', $phone)) $errors['Contain_number'] = 'Enter a valid phone number.';
		if (!$errors) {
			$clash = q_one('SELECT User_Name, Email FROM User WHERE (User_Name = ? OR Email = ?) AND User_ID <> ? LIMIT 1', [$name, $email, $uid]);
			if ($clash) {
				if (strcasecmp($clash['User_Name'], $name) === 0) $errors['User_Name'] = 'That username is taken.';
				else $errors['Email'] = 'Another account uses this email.';
			}
		}
		if ($errors) json_out(false, ['fields' => $errors], 'Please check the highlighted fields.', 422);

		q('UPDATE User SET User_Name = ?, Email = ?, Contain_number = ? WHERE User_ID = ?', [$name, $email, $phone ?: null, $uid]);
		$_SESSION['User_name'] = $name;
		json_out(true, ['User_Name' => $name, 'Email' => $email, 'Contain_number' => $phone]);

	case 'change_password':
		$current = (string) ($_POST['current'] ?? '');
		$new = (string) ($_POST['new'] ?? '');
		$confirm = (string) ($_POST['confirm'] ?? '');
		$saveHash = fn(string $h) => q('UPDATE User SET Password = ? WHERE User_ID = ?', [$h, $uid]);
		if (!check_password($current, $user['Password'], $saveHash)) {
			json_out(false, ['fields' => ['current' => 'Current password is wrong.']], 'Current password is wrong.', 422);
		}
		if ($p = password_problem($new)) json_out(false, ['fields' => ['new' => $p]], $p, 422);
		if ($new !== $confirm) json_out(false, ['fields' => ['confirm' => 'Passwords do not match.']], 'Passwords do not match.', 422);
		if (hash_equals($current, $new)) json_out(false, ['fields' => ['new' => 'Choose a different password.']], 'Choose a different password.', 422);
		$saveHash(password_hash($new, PASSWORD_DEFAULT));
		session_regenerate_id(true);
		json_out(true);

	case 'avatar':
		// Stored in User.Profile_Image (replaces the old CSV mapping).
		require __DIR__ . '/../../app/services/images.php';
		$dir = material_dir() . '/images/uploads';
		if (!is_dir($dir)) mkdir($dir, 0755, true);
		$name = 'avatar_' . $uid . '_' . bin2hex(random_bytes(4)) . '.jpg';
		if ($err = save_uploaded_jpeg($_FILES['avatar'] ?? [], $dir . '/' . $name, 512)) json_out(false, null, $err, 422);
		$old = (string) ($user['Profile_Image'] ?? '');
		$path = 'material/images/uploads/' . $name;
		q('UPDATE User SET Profile_Image = ? WHERE User_ID = ?', [$path, $uid]);
		// remove this user's previous upload (only files we created, never shared images)
		if (preg_match('#^material/images/uploads/avatar_' . $uid . '_[a-f0-9]{8}\.jpg$#', $old)) @unlink(dirname(__DIR__, 2) . '/' . $old);
		json_out(true, ['url' => url($path)]);

	case 'deactivate':
		// Soft delete: the row stays (orders reference it) but the account can no longer sign in.
		if (!check_password((string) ($_POST['password'] ?? ''), $user['Password'], fn() => null)) {
			json_out(false, null, 'Password is wrong. Account not deactivated.', 422);
		}
		q('UPDATE User SET Deleted_at = NOW() WHERE User_ID = ?', [$uid]);
		unset($_SESSION['user_id'], $_SESSION['User_name'], $_SESSION['cart']);
		session_regenerate_id(true);
		json_out(true, ['redirect' => url('index.php')]);

	default:
		json_out(false, null, 'Unknown action.', 400);
}
