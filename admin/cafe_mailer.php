<?php
/**
 * Email helper (PHPMailer over SMTP). Place at app/services/cafe_mailer.php
 *
 * 1) Fill in CAFE_MAIL_CONFIG below — use the SAME values your password-reset email already uses.
 *    (Gmail: smtp.gmail.com, port 587, tls, and an "App password", not your normal password.)
 * 2) PHPMailer is found automatically if it is in vendor/ (Composer) or in a PHPMailer/src folder
 *    in the project root. If yours lives somewhere else, add that path to $folders in cafe_mail_load().
 */

const CAFE_MAIL_CONFIG = [
	'host'      => 'smtp.gmail.com',
	'port'      => 587,
	'secure'    => 'tls',                       // 'tls' (587) or 'ssl' (465)
	'username'  => 'YOUR_EMAIL@gmail.com',      // <-- change
	'password'  => 'YOUR_APP_PASSWORD',         // <-- change (Gmail app password)
	'from'      => 'YOUR_EMAIL@gmail.com',      // <-- change
	'from_name' => 'CafeDash',
];

function cafe_mail_load(): bool
{
	if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) return true;
	$root = dirname(__DIR__, 2);
	if (is_file("$root/vendor/autoload.php")) {
		require_once "$root/vendor/autoload.php";
		return class_exists('\PHPMailer\PHPMailer\PHPMailer');
	}
	foreach (['PHPMailer/src', 'phpmailer/src', 'vendor/phpmailer/phpmailer/src'] as $dir) {
		if (is_file("$root/$dir/PHPMailer.php")) {
			require_once "$root/$dir/Exception.php";
			require_once "$root/$dir/PHPMailer.php";
			require_once "$root/$dir/SMTP.php";
			return true;
		}
	}
	return false;
}

/** Send one email. Returns true on success; never throws (errors go to the PHP error log). */
function cafe_send_mail(string $to, string $toName, string $subject, string $html, string $text = ''): bool
{
	if (!cafe_mail_load()) {
		error_log('mailer: PHPMailer not found.');
		return false;
	}
	try {
		$m = new \PHPMailer\PHPMailer\PHPMailer(true);
		$m->isSMTP();
		$m->Host = CAFE_MAIL_CONFIG['host'];
		$m->SMTPAuth = true;
		$m->Username = CAFE_MAIL_CONFIG['username'];
		$m->Password = CAFE_MAIL_CONFIG['password'];
		$m->SMTPSecure = CAFE_MAIL_CONFIG['secure'];
		$m->Port = CAFE_MAIL_CONFIG['port'];
		$m->Timeout = 10;
		$m->CharSet = 'UTF-8';
		$m->setFrom(CAFE_MAIL_CONFIG['from'], CAFE_MAIL_CONFIG['from_name']);
		$m->addAddress($to, $toName);
		$m->isHTML(true);
		$m->Subject = $subject;
		$m->Body = $html;
		$m->AltBody = $text !== '' ? $text : strip_tags($html);
		$m->send();
		return true;
	} catch (Throwable $e) {
		error_log('mailer: ' . $e->getMessage());
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
