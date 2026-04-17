<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$errors = [];
$success_message = '';
$email_or_username = '';

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

function build_reset_link(string $token): string {
	$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
	$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
	$path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
	return sprintf('%s://%s%s/_reset_password.php?token=%s', $scheme, $host, $path, urlencode($token));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$email_or_username = trim($_POST['email_or_username'] ?? '');

	if ($email_or_username === '') {
		$errors[] = 'Please enter your registered email or username.';
	}

	if (empty($errors)) {
		try {
			$pdo = get_pdo();
			ensure_reset_table($pdo);

			$stmt = $pdo->prepare('SELECT User_ID, Email FROM User WHERE User_Name = :input OR Email = :input LIMIT 1');
			$stmt->execute([':input' => $email_or_username]);
			$user = $stmt->fetch();

			if ($user) {
				$token = bin2hex(random_bytes(32));
				$token_hash = hash('sha256', $token);
				$expires_at = date('Y-m-d H:i:s', time() + 3600);

				$delete_stmt = $pdo->prepare('DELETE FROM password_resets WHERE user_id = :user_id');
				$delete_stmt->execute([':user_id' => $user['User_ID']]);

				$insert_stmt = $pdo->prepare(
					'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, :expires_at)'
				);
				$insert_stmt->execute([
					':user_id' => $user['User_ID'],
					':token_hash' => $token_hash,
					':expires_at' => $expires_at
				]);

				$reset_link = build_reset_link($token);

				$config_path = __DIR__ . '/../config/mail_config.php';
				if (!file_exists($config_path)) {
					throw new RuntimeException('Mail config file is missing.');
				}
				$mail_config = require $config_path;

				$autoload_path = __DIR__ . '/../vendor/autoload.php';
				if (!file_exists($autoload_path)) {
					throw new RuntimeException('PHPMailer is not installed. Please run composer require phpmailer/phpmailer.');
				}
				require_once $autoload_path;

				$mail = new PHPMailer(true);
				$mail->isSMTP();
				$mail->Host = $mail_config['host'];
				$mail->SMTPAuth = true;
				$mail->Username = $mail_config['username'];
				$mail->Password = $mail_config['password'];
				$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
				$mail->Port = (int) $mail_config['port'];

				$mail->setFrom($mail_config['from_email'], $mail_config['from_name']);
				$mail->addAddress($user['Email']);
				$mail->isHTML(true);
				$mail->Subject = 'CafeDash Password Reset';
				$mail->Body = '<p>You requested a password reset. Click the link below to reset your password:</p>'
					. '<p><a href="' . htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8') . '</a></p>'
					. '<p>This link expires in 1 hour.</p>';

				$mail->send();
			}

			$success_message = 'If the account exists, a reset link has been sent to the registered email.';
		} catch (Exception $e) {
			$errors[] = 'Email could not be sent. Please try again later.';
		} catch (Throwable $e) {
			$errors[] = 'System error: unable to process your request right now.';
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
