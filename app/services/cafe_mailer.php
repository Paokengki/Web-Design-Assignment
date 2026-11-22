<?php
/**
 * Email helper for café applications (PHPMailer over SMTP). Place at app/services/cafe_mailer.php
 *
 * It reuses the SAME settings and PHPMailer install as the "Forgot password" email:
 *   - config/mail.config.php   (returns ['host','port','username','password','from_email','from_name'])
 *   - vendor/autoload.php      (Composer: phpmailer/phpmailer)
 * so there is nothing extra to configure here.
 */

use PHPMailer\PHPMailer\PHPMailer;

/** Mail settings from config/mail.config.php, or null if the file is missing or incomplete. */
function cafe_mail_config(): ?array
{
	static $cfg = false;
	if ($cfg !== false) return $cfg;
	$cfg = null;
	$path = dirname(__DIR__, 2) . '/config/mail.config.php';
	if (!is_file($path)) {
		error_log('cafe_mailer: config/mail.config.php not found.');
		return $cfg;
	}
	$c = require $path;
	foreach (['host', 'port', 'username', 'password', 'from_email', 'from_name'] as $k) {
		if (!is_array($c) || !isset($c[$k]) || $c[$k] === '') {
			error_log("cafe_mailer: config/mail.config.php is missing '$k'.");
			return $cfg;
		}
	}
	return $cfg = $c;
}

/** Load PHPMailer from Composer. */
function cafe_mail_load(): bool
{
	if (class_exists(PHPMailer::class)) return true;
	$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
	if (!is_file($autoload)) {
		error_log('cafe_mailer: vendor/autoload.php not found (run: composer require phpmailer/phpmailer).');
		return false;
	}
	require_once $autoload;
	return class_exists(PHPMailer::class);
}

/** Configure a PHPMailer instance exactly like the Forgot-password page does. */
function cafe_mail_new(array $cfg): PHPMailer
{
	$m = new PHPMailer(true);
	$m->isSMTP();
	$m->Host = $cfg['host'];
	$m->SMTPAuth = true;
	$m->Username = $cfg['username'];
	$m->Password = $cfg['password'];
	$m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
	$m->Port = (int) $cfg['port'];
	$m->Timeout = 10;
	$m->CharSet = 'UTF-8';
	$m->setFrom($cfg['from_email'], $cfg['from_name']);
	return $m;
}

/** Send one email. Returns true on success; never throws (errors go to the PHP error log). */
function cafe_send_mail(string $to, string $toName, string $subject, string $html, string $text = ''): bool
{
	$cfg = cafe_mail_config();
	if (!$cfg || !cafe_mail_load()) return false;
	try {
		$m = cafe_mail_new($cfg);
		$m->addAddress($to, $toName);
		$m->isHTML(true);
		$m->Subject = $subject;
		$m->Body = $html;
		$m->AltBody = $text !== '' ? $text : strip_tags($html);
		$m->send();
		return true;
	} catch (Throwable $e) {
		error_log('cafe_mailer: ' . $e->getMessage());
		return false;
	}
}

/** Absolute link for use inside an email. */
function cafe_mail_link(string $path): string
{
	$u = url($path);
	if (preg_match('#^https?://#i', $u)) return $u;
	$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
	return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8000') . '/' . ltrim($u, '/');
}

/** "Your café was approved" email with the sign-in details. */
function cafe_send_application_approved(string $to, string $ownerName, string $cafeName, string $tempPassword): bool
{
	$login = cafe_mail_link('tracking/kitchen/login.php');
	$o = htmlspecialchars($ownerName, ENT_QUOTES, 'UTF-8');
	$c = htmlspecialchars($cafeName, ENT_QUOTES, 'UTF-8');
	$e = htmlspecialchars($to, ENT_QUOTES, 'UTF-8');
	$p = htmlspecialchars($tempPassword, ENT_QUOTES, 'UTF-8');
	$l = htmlspecialchars($login, ENT_QUOTES, 'UTF-8');

	$html = "<div style=\"font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#222\">
		<h2 style=\"margin-bottom:4px\">Welcome to CafeDash, $c!</h2>
		<p>Hi $o, your café application has been <strong>approved</strong>.</p>
		<p>You can now sign in to the kitchen view with:</p>
		<table style=\"border-collapse:collapse;margin:12px 0\">
			<tr><td style=\"padding:6px 12px 6px 0;color:#666\">Email</td><td><strong>$e</strong></td></tr>
			<tr><td style=\"padding:6px 12px 6px 0;color:#666\">Temporary password</td><td><strong style=\"font-family:monospace;font-size:16px\">$p</strong></td></tr>
		</table>
		<p><a href=\"$l\" style=\"background:#f5a623;color:#111;padding:10px 18px;border-radius:20px;text-decoration:none;font-weight:bold\">Sign in</a></p>
		<p style=\"color:#666;font-size:13px\">For your security, please change this password after your first sign-in. Our team will contact you to finish setting up your café’s photo and menu.</p>
	</div>";

	$text = "Hi $ownerName,\n\nYour café \"$cafeName\" has been approved on CafeDash.\n\nSign in: $login\nEmail: $to\nTemporary password: $tempPassword\n\nPlease change this password after your first sign-in.";

	return cafe_send_mail($to, $ownerName, 'Your café is approved — CafeDash sign-in details', $html, $text);
}
