<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. Logic to include shared DB connection from base.php if preferred, 
// but for standalone functionality, we'll use your PDO helper.
$errors = [];
$success_message = '';

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

// Reuse the existing password_resets table
function build_admin_reset_link(string $token): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    // Note: link points to _admin_reset_password.php
    return sprintf('%s://%s%s/_admin_reset_password.php?token=%s', $scheme, $host, $path, urlencode($token));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_or_name = trim($_POST['email_or_name'] ?? '');

    if ($email_or_name === '') {
        $errors[] = 'Please enter your admin email or username.';
    }

    if (empty($errors)) {
        try {
            $pdo = get_pdo();

            // QUERY CHANGE: Target the Admin table instead of User
            $stmt = $pdo->prepare('SELECT Admin_ID, Email FROM Admin WHERE Name = :input OR Email = :input LIMIT 1');
            $stmt->execute([':input' => $email_or_name]);
            $admin = $stmt->fetch();

            if ($admin) {
                $token = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $token);
                $expires_at = date('Y-m-d H:i:s', time() + 3600);

                // Insert into the shared password_resets table
                // We will use a unique identifier or simply rely on the token hash 
                // but since the table uses 'user_id', we'll store the Admin_ID there.
                $insert_stmt = $pdo->prepare(
                    'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:id, :token_hash, :expires_at)'
                );
                $insert_stmt->execute([
                    ':id' => $admin['Admin_ID'],
                    ':token_hash' => $token_hash,
                    ':expires_at' => $expires_at
                ]);

                $reset_link = build_admin_reset_link($token);

                // --- Email Logic (Keep your existing PHPMailer config) ---
                $config_path = __DIR__ . '/../../config/mail.config.php';
                $autoload_path = __DIR__ . '/../../vendor/autoload.php';
                
                require_once $autoload_path;
                $mail_config = require $config_path;

                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = $mail_config['host'];
                $mail->SMTPAuth = true;
                $mail->Username = $mail_config['username'];
                $mail->Password = $mail_config['password'];
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = (int)$mail_config['port'];

                $mail->setFrom($mail_config['from_email'], 'CafeDash Admin Security');
                $mail->addAddress($admin['Email']);
                $mail->isHTML(true);
                $mail->Subject = 'Admin Password Reset Request';
                $mail->Body = "<p>Hello Admin, click the link to reset your dashboard password:</p>
                               <p><a href='$reset_link'>$reset_link</a></p>";
                $mail->send();
            }

            $success_message = 'If the admin account exists, a reset link has been sent.';
        } catch (Throwable $e) {
            $errors[] = 'System error: ' . $e->getMessage();
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
	<title>CafeDash | Forgot Password</title>
	<link rel="stylesheet" href="../Css/forgot.css">
	<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>

<body>
	<video autoplay muted loop id="myVideo">
		<source src="../material/images/coffee login.mp4" type="video/mp4">
	</video>

	<div class="forgot-password-content">
		<div class="forgot-password-wrapper">
			<form action="" method="post">
				<h1>Forgot Password</h1>
				<div class="logo">
					<img src="../material/images/logo.png" alt="logo" style="width:200px;height:200px;">
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
					<input type="text" name="email_or_username" placeholder="Enter your email or username" value="<?php echo htmlspecialchars($email_or_username, ENT_QUOTES, 'UTF-8'); ?>" required>
					<i class='bx bx-envelope'></i>
				</div>

				<button type="submit" class="forgot-password-btn">Send Reset Link</button>

				<div class="back-to-login">
					<p>Remembered your password? <a href="../_member_login.php">Login</a></p>
				</div>
			</form>
		</div>
	</div>
</body>
</html>