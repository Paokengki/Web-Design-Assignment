<?php
require __DIR__ . '/../app/layout/master.php';

if (current_user()) redirect('customer/dashboard.php');

$errors = [];
$old = ['username' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$old = [
		'username' => trim((string) ($_POST['username'] ?? '')),
		'email'    => trim((string) ($_POST['email'] ?? '')),
		'phone'    => trim((string) ($_POST['phone'] ?? '')),
	];
	$password = (string) ($_POST['password'] ?? '');
	$confirm  = (string) ($_POST['confirm'] ?? '');

	if (!csrf_valid()) $errors['form'] = 'Your session expired. Please try again.';
	if (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $old['username'])) $errors['username'] = '3–30 letters, numbers, dots or underscores.';
	if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';
	if ($old['phone'] !== '' && !preg_match('/^[0-9+\-\s]{9,20}$/', $old['phone'])) $errors['phone'] = 'Enter a valid phone number.';
	if ($p = password_problem($password)) $errors['password'] = $p;
	if ($password !== $confirm) $errors['confirm'] = 'Passwords do not match.';

	if (!$errors) {
		$taken = q_one('SELECT User_Name, Email FROM User WHERE User_Name = ? OR Email = ? LIMIT 1', [$old['username'], $old['email']]);
		if ($taken) {
			if (strcasecmp($taken['User_Name'], $old['username']) === 0) $errors['username'] = 'That username is taken.';
			else $errors['email'] = 'That email already has an account.';
		}
	}

	if (!$errors) {
		q('INSERT INTO User (User_Name, Email, Contain_number, Password) VALUES (?, ?, ?, ?)', [
			$old['username'], $old['email'], $old['phone'] ?: null, password_hash($password, PASSWORD_DEFAULT),
		]);
		session_regenerate_id(true);
		$_SESSION['user_id'] = (int) db()->insert_id;
		$_SESSION['User_name'] = $old['username'];
		flash('success', 'Account created. Add a delivery address to get started.');
		redirect('customer/dashboard.php');
	}
}

function err(array $errors, string $k): string
{
	return isset($errors[$k]) ? '<span class="field-error">' . e($errors[$k]) . '</span>' : '';
}
function inv(array $errors, string $k): string
{
	return isset($errors[$k]) ? ' aria-invalid="true"' : '';
}

layout_start(['title' => 'Create account', 'nav' => 'none', 'body_class' => 'auth', 'styles' => ['pages/auth.css']]);
?>
<div class="auth-shell">
	<?php $visual_heading = 'Every ringgit brews a point.'; $visual_frame = 60; require __DIR__ . '/../app/layout/auth_visual.php'; ?>
	<div class="auth-panel">
		<div class="auth-card">
			<span class="eyebrow" data-reveal>Join CafeDash</span>
			<h1 data-reveal>Create account</h1>
			<p data-reveal>Takes 30 seconds. Your first voucher is waiting.</p>
			<?php if (isset($errors['form'])): ?><div class="flash flash-error" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>
			<form method="post" action="" novalidate data-reveal>
				<?= csrf_field() ?>
				<div class="form-grid">
					<label class="field"><span>Username</span>
						<input class="input" name="username" value="<?= e($old['username']) ?>" autocomplete="username" required<?= inv($errors, 'username') ?>>
						<?= err($errors, 'username') ?>
					</label>
					<label class="field"><span>Phone <em class="tiny">(optional)</em></span>
						<input class="input" name="phone" type="tel" value="<?= e($old['phone']) ?>" autocomplete="tel" placeholder="012-345 6789"<?= inv($errors, 'phone') ?>>
						<?= err($errors, 'phone') ?>
					</label>
					<label class="field span-2"><span>Email</span>
						<input class="input" name="email" type="email" value="<?= e($old['email']) ?>" autocomplete="email" required<?= inv($errors, 'email') ?>>
						<?= err($errors, 'email') ?>
					</label>
					<label class="field span-2"><span>Password</span>
						<span class="input-wrap">
							<input class="input" type="password" name="password" autocomplete="new-password" required data-strength="#reg-strength"<?= inv($errors, 'password') ?>>
							<button type="button" class="icon-btn" data-toggle-password aria-label="Show password"><svg viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button>
						</span>
						<?= err($errors, 'password') ?>
						<div id="reg-strength"></div>
					</label>
					<label class="field span-2"><span>Confirm password</span>
						<input class="input" type="password" name="confirm" autocomplete="new-password" required<?= inv($errors, 'confirm') ?>>
						<?= err($errors, 'confirm') ?>
					</label>
				</div>
				<button class="btn btn-primary btn-lg btn-block" type="submit" data-magnetic>Create account</button>
			</form>
			<p class="auth-switch">Already a member? <a href="<?= e(url('auth/login.php')) ?>">Sign in</a></p>
		</div>
	</div>
</div>
<?php layout_end(['pages/mascots.js']);
