<?php
/**
 * Outgoing email via PHPMailer + SMTP.
 * Requires composer install (phpmailer/phpmailer) and config/mail.config.php returning
 *   ['host' => 'smtp.gmail.com', 'port' => 587, 'username' => '…', 'password' => '…', 'from_email' => '…', 'from_name' => 'CafeDash']
 * (the same file the old forgot-password page used).
 */
function mail_config(): ?array
{
	$root = dirname(__DIR__, 2);
	$file = $root . '/config/mail.config.php';
	if (!is_file($file) || !is_file($root . '/vendor/autoload.php')) return null;
	$c = require $file;
	return is_array($c) && !empty($c['host']) ? $c : null;
}

function mail_ready(): bool
{
	return mail_config() !== null;
}

/** Local development only: show links on screen when email isn't set up. Never true on a real host. */
function is_local_dev(): bool
{
	$host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
	return in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);
}

function send_mail(string $to, string $subject, string $html): bool
{
	$c = mail_config();
	if (!$c) return false;
	require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
	try {
		$m = new PHPMailer\PHPMailer\PHPMailer(true);
		$m->isSMTP();
		$m->Host = $c['host'];
		$m->SMTPAuth = true;
		$m->Username = $c['username'];
		$m->Password = $c['password'];
		$m->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
		$m->Port = (int) $c['port'];
		$m->CharSet = 'UTF-8';
		$m->setFrom($c['from_email'], $c['from_name'] ?? APP_NAME);
		$m->addAddress($to);
		$m->isHTML(true);
		$m->Subject = $subject;
		$m->Body = $html;
		$m->AltBody = trim(strip_tags(str_replace(['<br>', '</p>'], "\n", $html)));
		$m->send();
		return true;
	} catch (Throwable $e) {
		error_log('CafeDash mail error: ' . $e->getMessage());
		return false;
	}
}
