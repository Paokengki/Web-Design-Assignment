<?php
declare(strict_types=1);

$errors = [];
$success_message = '';
$token = trim($_GET['token'] ?? '');

function get_pdo(): PDO {
    return new PDO(
        'mysql:host=localhost;dbname=cafedash_db;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
}

function ensure_reset_table(PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )'
    );
}

function find_reset(PDO $pdo, string $token): ?array {
    $token_hash = hash('sha256', $token);
    $stmt = $pdo->prepare('SELECT user_id, expires_at FROM password_resets WHERE token_hash = :token_hash LIMIT 1');
    $stmt->execute([':token_hash' => $token_hash]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    if (strtotime($row['expires_at']) < time()) {
        $delete_stmt = $pdo->prepare('DELETE FROM password_resets WHERE token_hash = :token_hash');
        $delete_stmt->execute([':token_hash' => $token_hash]);
        return null;
    }

    return $row;
}

// Require a valid reset token before accepting any password change.
if ($token === '') {
    $errors[] = 'Reset token is missing or invalid.';
}

// Validate the new password pair, then update the account and clear the token.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $password = (string) ($_POST['password'] ?? '');
    $confirm_password = (string) ($_POST['confirm_password'] ?? '');

    if ($password === '') {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($confirm_password === '') {
        $errors[] = 'Please confirm your password.';
    } elseif ($password !== $confirm_password) {
        $errors[] = 'Password and Confirm Password do not match.';
    }

    if (empty($errors)) {
        try {
            $pdo = get_pdo();
            ensure_reset_table($pdo);
            $reset_row = find_reset($pdo, $token);

            if (!$reset_row) {
                $errors[] = 'Reset token is invalid or has expired.';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update_stmt = $pdo->prepare('UPDATE User SET Password = :password WHERE User_ID = :user_id');
                $update_stmt->execute([
                    ':password' => $hashed_password,
                    ':user_id' => $reset_row['user_id']
                ]);

                $delete_stmt = $pdo->prepare('DELETE FROM password_resets WHERE user_id = :user_id');
                $delete_stmt->execute([':user_id' => $reset_row['user_id']]);

                $success_message = 'Your password has been updated. You can now login.';
            }
        } catch (Throwable $e) {
            $errors[] = 'System error: unable to update your password right now.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CafeDash | Reset Password</title>
    <link rel="stylesheet" href="../../Css/reset.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <video autoplay muted loop id="myVideo">
        <source src="../../material/images/coffee login.mp4" type="video/mp4">
    </video>

    <div class="reset-content">
        <div class="reset-wrapper">
            <form action="?token=<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>" method="post">
                <h1>Reset Password</h1>
                <div class="logo">
                    <img src="../../material/images/logo.png" alt="logo" style="width:200px;height:200px;">
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="error-box">
                        <i class='bx bx-error-circle'></i>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($success_message !== ''): ?>
                    <div class="success-box">
                        <i class='bx bx-check-circle'></i>
                        <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <div class="textbox">
                    <input type="password" name="password" placeholder="New Password" required>
                    <i class='bx bxs-lock-alt'></i>
                </div>

                <div class="textbox">
                    <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
                    <i class='bx bxs-lock-alt'></i>
                </div>

                <button type="submit" class="reset-btn">Update Password</button>

                <div class="back-to-login">
                    <p>Back to <a href="../_member_login.php">Login</a></p>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
