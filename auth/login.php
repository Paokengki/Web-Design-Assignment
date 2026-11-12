<?php
require __DIR__ . '/../app/layout/master.php';

if (current_user()) redirect('customer/dashboard.php');

const MAX_ATTEMPTS = 5;
const LOCK_SECONDS = 600;

$error = '';
$identity = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$identity = trim((string) ($_POST['identity'] ?? ''));
	$password = (string) ($_POST['password'] ?? '');
	$attempts = $_SESSION['login_attempts'] ?? ['n' => 0, 't' => time()];
	if (time() - $attempts['t'] > LOCK_SECONDS) $attempts = ['n' => 0, 't' => time()];

	if (!csrf_valid()) {
		$error = 'Your session expired. Please try again.';
	} elseif ($attempts['n'] >= MAX_ATTEMPTS) {
		$error = 'Too many attempts. Try again in a few minutes.';
	} elseif ($identity === '' || $password === '') {
		$error = 'Enter your username or email and password.';
	} else {
		$user = q_one('SELECT User_ID, User_Name, Password, Suspend, Deleted_at FROM User WHERE User_Name = ? OR Email = ? LIMIT 1', [$identity, $identity]);
		$ok = $user && check_password($password, $user['Password'], function (string $hash) use ($user) {
			q('UPDATE User SET Password = ? WHERE User_ID = ?', [$hash, (int) $user['User_ID']]);
		});

		if (!$ok) {
			$attempts['n']++;
			$_SESSION['login_attempts'] = $attempts;
			$error = 'Wrong username/email or password.';
		} elseif ($user['Deleted_at'] !== null) {
			$error = 'This account was deactivated. Contact us to restore it.';
		} elseif ((int) $user['Suspend'] === 1) {
			$error = 'Your account is suspended. Please contact the administrator.';
		} else {
			unset($_SESSION['login_attempts']);
			session_regenerate_id(true);
			$_SESSION['user_id'] = (int) $user['User_ID'];
			$_SESSION['User_name'] = $user['User_Name']; // used by the older pages
			flash('success', 'Welcome back, ' . $user['User_Name'] . '.');
			// ?next= lets checkout send people back after signing in; only plain internal page paths are allowed.
			$next = (string) ($_GET['next'] ?? '');
			redirect(preg_match('#^customer/[a-z_]+\.php$#', $next) ? $next : 'customer/dashboard.php');
		}
	}
}

layout_start(['title' => 'Sign in', 'nav' => 'none', 'body_class' => 'auth', 'styles' => ['pages/auth.css']]);
?>
<div class="auth-shell">
	<?php $visual_heading = 'Good to see you again.'; require __DIR__ . '/../app/layout/auth_visual.php'; ?>
	<div class="auth-panel">
		<div class="auth-card">
			<div class="auth-tabs" data-reveal>
				<a class="is-active" href="<?= e(url('auth/login.php')) ?>">Member</a>
				<a href="<?= e(url('auth/staff_login.php')) ?>">Staff</a>
			</div>
			<h1 data-reveal>Sign in</h1>
			<p data-reveal>Pick up where your last order left off.</p>
			<?php if ($error): ?><div class="flash flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>
			<form method="post" action="" novalidate data-reveal>
				<?= csrf_field() ?>
				<label class="field"><span>Username or email</span>
					<input class="input" name="identity" value="<?= e($identity) ?>" autocomplete="username" required autofocus>
				</label>
				<label class="field"><span>Password</span>
					<span class="input-wrap">
						<input class="input" type="password" name="password" autocomplete="current-password" required>
						<button type="button" class="icon-btn" data-toggle-password aria-label="Show password"><svg viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button>
					</span>
				</label>
				<div style="display:flex;justify-content:flex-end"><a class="tiny" href="<?= e(url('auth/forgot.php')) ?>">Forgot password?</a></div>
				<button class="btn btn-primary btn-lg btn-block" type="submit" data-magnetic>Sign in</button>
			</form>
			<p class="auth-switch">New here? <a href="<?= e(url('auth/register.php')) ?>">Create an account</a></p>
		</div>
	</div>
</div>
<?php layout_end(['pages/mascots.js']);
