<?php
/** Choose a new password from an emailed link — replaces login/member_login/_reset_password.php. */
require __DIR__ . '/../app/layout/master.php';

$token = (string) ($_GET['token'] ?? '');
$row = null;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
	$row = q_one('SELECT user_id FROM password_resets WHERE token_hash = ? AND expires_at > NOW()', [hash('sha256', $token)]);
}

$errors = [];
$done = false;
if ($row && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$password = (string) ($_POST['password'] ?? '');
	if (!csrf_valid()) $errors['form'] = 'Your session expired. Please try again.';
	elseif ($p = password_problem($password)) $errors['password'] = $p;
	elseif ($password !== (string) ($_POST['confirm'] ?? '')) $errors['confirm'] = 'Passwords do not match.';
	if (!$errors) {
		q('UPDATE User SET Password = ? WHERE User_ID = ?', [password_hash($password, PASSWORD_DEFAULT), (int) $row['user_id']]);
		q('DELETE FROM password_resets WHERE user_id = ?', [(int) $row['user_id']]);
		flash('success', 'Password updated. Sign in with your new password.');
		redirect('auth/login.php');
	}
}

layout_start(['title' => 'New password', 'nav' => 'none', 'body_class' => 'auth', 'styles' => ['pages/auth.css']]);
?>
<div class="auth-shell">
	<?php $visual_heading = 'Fresh start.'; $visual_text = 'Pick something you haven’t used before.'; $visual_frame = 260; require __DIR__ . '/../app/layout/auth_visual.php'; ?>
	<div class="auth-panel">
		<div class="auth-card">
			<h1 data-reveal>New password</h1>
			<?php if (!$row): ?>
				<div class="flash flash-error" role="alert">This link is invalid or has expired. Links work once, for one hour.</div>
				<a class="btn btn-primary btn-block" href="<?= e(url('auth/forgot.php')) ?>">Get a new link</a>
			<?php else: ?>
				<p data-reveal>Use at least 8 characters with a letter and a number.</p>
				<?php if (isset($errors['form'])): ?><div class="flash flash-error" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>
				<form method="post" action="" novalidate data-reveal>
					<?= csrf_field() ?>
					<label class="field"><span>New password</span>
						<span class="input-wrap">
							<input class="input" type="password" name="password" autocomplete="new-password" required autofocus data-strength="#reset-strength"<?= isset($errors['password']) ? ' aria-invalid="true"' : '' ?>>
							<button type="button" class="icon-btn" data-toggle-password aria-label="Show password"><svg viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button>
						</span>
						<?php if (isset($errors['password'])): ?><span class="field-error"><?= e($errors['password']) ?></span><?php endif; ?>
						<div id="reset-strength"></div>
					</label>
					<label class="field"><span>Confirm password</span>
						<input class="input" type="password" name="confirm" autocomplete="new-password" required<?= isset($errors['confirm']) ? ' aria-invalid="true"' : '' ?>>
						<?php if (isset($errors['confirm'])): ?><span class="field-error"><?= e($errors['confirm']) ?></span><?php endif; ?>
					</label>
					<button class="btn btn-primary btn-lg btn-block" type="submit" data-magnetic>Save password</button>
				</form>
			<?php endif; ?>
		</div>
	</div>
</div>
<?php layout_end();
