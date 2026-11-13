<?php
/**
 * Kitchen Staff login — reuses the existing Staff table and session system.
 *
 * This is a SEPARATE login page because the existing auth/staff_login.php
 * restricts access to Managers and Super Admins only:
 *   "The control panel is for Managers and Super Admins."
 *
 * Kitchen Staff log in here with their Staff email + password.
 * The session key is the same: $_SESSION['staff_id'].
 *
 * No duplicate auth system — same Staff table, same password hashing,
 * same session mechanism as auth/staff_login.php.
 */
require_once __DIR__ . '/../../app/bootstrap.php';

// If already logged in as kitchen staff, go to the dashboard.
$staff = current_staff();
if ($staff && $staff['Role'] === 'Kitchen Staff') {
	redirect('tracking/kitchen/orders.php');
}
// Managers/Super Admins get sent to the admin panel.
if ($staff && in_array($staff['Role'], ADMIN_ROLES, true)) {
	redirect('tracking/manager/deliveries.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$email = trim((string) ($_POST['email'] ?? ''));
	$password = (string) ($_POST['password'] ?? '');
	$attempts = $_SESSION['kitchen_attempts'] ?? ['n' => 0, 't' => time()];
	if (time() - $attempts['t'] > 600) $attempts = ['n' => 0, 't' => time()];

	if (!csrf_valid()) {
		$error = 'Your session expired. Please try again.';
	} elseif ($attempts['n'] >= 5) {
		$error = 'Too many attempts. Try again in a few minutes.';
	} else {
		$row = q_one('SELECT Staff_ID, Name, Password, Role, Status, Assigned_Restaurant_ID FROM Staff WHERE Email = ? LIMIT 1', [$email]);
		$ok = $row && check_password($password, $row['Password'], function (string $hash) use ($row) {
			q('UPDATE Staff SET Password = ? WHERE Staff_ID = ?', [$hash, (int) $row['Staff_ID']]);
		});

		if (!$ok) {
			$attempts['n']++;
			$_SESSION['kitchen_attempts'] = $attempts;
			$error = 'Wrong email or password.';
		} elseif ($row['Status'] !== 'Active') {
			$error = 'This staff account is ' . strtolower($row['Status']) . '.';
		} elseif ($row['Role'] !== 'Kitchen Staff') {
			// Redirect couriers and managers to their dashboards.
			if ($row['Role'] === 'Courier') {
				unset($_SESSION['kitchen_attempts']);
				session_regenerate_id(true);
				$_SESSION['staff_id'] = (int) $row['Staff_ID'];
				q('UPDATE Staff SET Last_login_at = NOW() WHERE Staff_ID = ?', [(int) $row['Staff_ID']]);
				redirect('tracking/courier/deliveries.php');
			}
			if (in_array($row['Role'], ADMIN_ROLES, true)) {
				unset($_SESSION['kitchen_attempts']);
				session_regenerate_id(true);
				$_SESSION['staff_id'] = (int) $row['Staff_ID'];
				q('UPDATE Staff SET Last_login_at = NOW() WHERE Staff_ID = ?', [(int) $row['Staff_ID']]);
				redirect('admin/overview.php');
			}
			$error = 'This login is for Kitchen Staff. Your role: ' . $row['Role'] . '.';
		} else {
			unset($_SESSION['kitchen_attempts']);
			session_regenerate_id(true);
			$_SESSION['staff_id'] = (int) $row['Staff_ID'];
			q('UPDATE Staff SET Last_login_at = NOW() WHERE Staff_ID = ?', [(int) $row['Staff_ID']]);
			redirect('tracking/kitchen/orders.php');
		}
	}
}

// Render the page using the existing master layout.
$GLOBALS['__layout_nav'] = 'none';
require_once __DIR__ . '/../../app/layout/master.php';
layout_start(['title' => 'Kitchen Staff Sign In', 'nav' => 'none', 'body_class' => 'auth', 'styles' => ['pages/auth.css']]);
?>
<link rel="stylesheet" href="<?= e(asset('tracking/assets/css/tracking.css')) ?>">

<div class="track-login-page">
	<div class="track-login-card">
		<div class="track-login-tabs">
			<a class="track-login-tab is-active" href="<?= e(url('tracking/kitchen/login.php')) ?>">Kitchen</a>
			<a class="track-login-tab" href="<?= e(url('tracking/courier/login.php')) ?>">Courier</a>
		</div>
		<h1>Kitchen Dashboard</h1>
		<p>Sign in to view incoming orders for your caf&eacute;.</p>
		<?php if ($error): ?><div class="flash flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>
		<form method="post" action="" novalidate data-reveal>
			<?= csrf_field() ?>
			<label class="field"><span>Work email</span>
				<input class="input" type="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
			</label>
			<label class="field"><span>Password</span>
				<span class="input-wrap">
					<input class="input" type="password" name="password" autocomplete="current-password" required>
					<button type="button" class="icon-btn" data-toggle-password aria-label="Show password">
						<svg viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
					</button>
				</span>
			</label>
			<button class="btn btn-primary btn-lg btn-block" type="submit" data-magnetic>Sign in</button>
		</form>
		<p style="margin-top:16px;font-size:12px;opacity:.5;text-align:center;">
			Manager? <a href="<?= e(url('auth/staff_login.php')) ?>" style="color:inherit;text-decoration:underline;">Admin sign in</a>
		</p>
	</div>
</div>
<?php layout_end();
