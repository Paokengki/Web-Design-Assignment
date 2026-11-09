<?php
require __DIR__ . '/../app/layout/master.php';

$staff = current_staff();
if ($staff && in_array($staff['Role'], ADMIN_ROLES, true)) redirect('admin/overview.php');

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$email = trim((string) ($_POST['email'] ?? ''));
	$password = (string) ($_POST['password'] ?? '');
	$attempts = $_SESSION['staff_attempts'] ?? ['n' => 0, 't' => time()];
	if (time() - $attempts['t'] > 600) $attempts = ['n' => 0, 't' => time()];

	if (!csrf_valid()) {
		$error = 'Your session expired. Please try again.';
	} elseif ($attempts['n'] >= 5) {
		$error = 'Too many attempts. Try again in a few minutes.';
	} else {
		$row = q_one('SELECT Staff_ID, Name, Password, Role, Status FROM Staff WHERE Email = ? LIMIT 1', [$email]);
		$ok = $row && check_password($password, $row['Password'], function (string $hash) use ($row) {
			q('UPDATE Staff SET Password = ? WHERE Staff_ID = ?', [$hash, (int) $row['Staff_ID']]);
		});
		if (!$ok) {
			$attempts['n']++;
			$_SESSION['staff_attempts'] = $attempts;
			$error = 'Wrong email or password.';
		} elseif ($row['Status'] !== 'Active') {
			$error = 'This staff account is ' . strtolower($row['Status']) . '.';
		} elseif (!in_array($row['Role'], ADMIN_ROLES, true)) {
			$error = 'The control panel is for Managers and Super Admins. Your role: ' . $row['Role'] . '.';
		} else {
			unset($_SESSION['staff_attempts']);
			session_regenerate_id(true);
			$_SESSION['staff_id'] = (int) $row['Staff_ID'];
			q('UPDATE Staff SET Last_login_at = NOW() WHERE Staff_ID = ?', [(int) $row['Staff_ID']]);
			redirect('admin/overview.php');
		}
	}
}

layout_start(['title' => 'Staff sign in', 'nav' => 'none', 'body_class' => 'auth', 'styles' => ['pages/auth.css']]);
?>
<div class="auth-shell">
	<?php $visual_heading = 'Behind the counter.'; $visual_text = 'Run promotions and manage the team from one control panel.'; $visual_tag = 'Control'; $visual_frame = 230; require __DIR__ . '/../app/layout/auth_visual.php'; ?>
	<div class="auth-panel">
		<div class="auth-card">
			<div class="auth-tabs" data-reveal>
				<a href="<?= e(url('auth/login.php')) ?>">Member</a>
				<a class="is-active" href="<?= e(url('auth/staff_login.php')) ?>">Staff</a>
			</div>
			<h1 data-reveal>Control panel</h1>
			<p data-reveal>Managers and Super Admins only.</p>
			<?php if ($error): ?><div class="flash flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>
			<form method="post" action="" novalidate data-reveal>
				<?= csrf_field() ?>
				<label class="field"><span>Work email</span>
					<input class="input" type="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
				</label>
				<label class="field"><span>Password</span>
					<span class="input-wrap">
						<input class="input" type="password" name="password" autocomplete="current-password" required>
						<button type="button" class="icon-btn" data-toggle-password aria-label="Show password"><svg viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button>
					</span>
				</label>
				<button class="btn btn-primary btn-lg btn-block" type="submit" data-magnetic>Sign in</button>
			</form>
		</div>
	</div>
</div>
<?php layout_end();
