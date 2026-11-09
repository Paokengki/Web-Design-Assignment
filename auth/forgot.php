<?php
/** Forgot password — replaces login/member_login/_forgot.php. Emails a one-hour, single-use link. */
require __DIR__ . '/../app/layout/master.php';
require __DIR__ . '/../app/services/mailer.php';

$sent = false;
$devLink = null;
$error = '';
$identity = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$identity = trim((string) ($_POST['identity'] ?? ''));
	$last = $_SESSION['reset_requested_at'] ?? 0;
	if (!csrf_valid()) {
		$error = 'Your session expired. Please try again.';
	} elseif ($identity === '') {
		$error = 'Enter your username or email.';
	} elseif (time() - $last < 60) {
		$error = 'Please wait a minute before asking for another link.';
	} else {
		$_SESSION['reset_requested_at'] = time();
		$user = q_one('SELECT User_ID, User_Name, Email FROM User WHERE (User_Name = ? OR Email = ?) AND Deleted_at IS NULL LIMIT 1', [$identity, $identity]);
		if ($user && $user['Email']) {
			$token = bin2hex(random_bytes(32));
			q('DELETE FROM password_resets WHERE user_id = ?', [(int) $user['User_ID']]);
			q('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)', [(int) $user['User_ID'], hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600)]);
			$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
			$link = $scheme . '://' . $_SERVER['HTTP_HOST'] . url('auth/reset.php?token=' . $token);
			$html = '<p>Hi ' . e($user['User_Name']) . ',</p><p>Someone asked to reset your CafeDash password. If that was you, use this link within the next hour:</p>'
				. '<p><a href="' . e($link) . '">' . e($link) . '</a></p><p>If it wasn’t you, ignore this email — your password stays the same.</p>';
			if (!send_mail($user['Email'], 'Reset your CafeDash password', $html) && !mail_ready() && is_local_dev()) {
				$devLink = $link; // email not configured: show the link on localhost only
			}
		}
		// Same answer whether or not the account exists, so nobody can probe for emails.
		$sent = true;
	}
}

layout_start(['title' => 'Forgot password', 'nav' => 'none', 'body_class' => 'auth', 'styles' => ['pages/auth.css']]);
?>
<div class="auth-shell">
	<?php $visual_heading = 'Happens to the best of us.'; $visual_text = 'We’ll email you a link to choose a new password.'; $visual_frame = 150; require __DIR__ . '/../app/layout/auth_visual.php'; ?>
	<div class="auth-panel">
		<div class="auth-card">
			<a class="back-link" href="<?= e(url('auth/login.php')) ?>"><?= icon('arrow-left') ?>Back to sign in</a>
			<h1 data-reveal>Reset password</h1>
			<?php if ($sent): ?>
				<div class="flash flash-success" role="status">If that account exists, a reset link is on its way. Check your inbox (and spam).</div>
				<?php if ($devLink): ?>
					<div class="flash">
						<strong>Developer mode:</strong> email isn’t configured (<code>config/mail.config.php</code>), so here’s the link instead.
						This only appears on localhost.<br><a class="grad-text" href="<?= e($devLink) ?>"><b>Open reset link</b></a>
					</div>
				<?php endif; ?>
			<?php else: ?>
				<p data-reveal>Enter the username or email you signed up with.</p>
				<?php if ($error): ?><div class="flash flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>
				<form method="post" action="" novalidate data-reveal>
					<?= csrf_field() ?>
					<label class="field"><span>Username or email</span>
						<input class="input" name="identity" value="<?= e($identity) ?>" autocomplete="username" required autofocus>
					</label>
					<button class="btn btn-primary btn-lg btn-block" type="submit" data-magnetic>Send reset link</button>
				</form>
			<?php endif; ?>
		</div>
	</div>
</div>
<?php layout_end();
