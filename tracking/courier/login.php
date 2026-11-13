<?php
/**
 * Courier login — reuses the existing Staff table and session system.
 *
 * Same mechanism as the kitchen login: Staff email + password,
 * session key $_SESSION['staff_id'], same password hashing.
 *
 * Couriers are redirected to the courier delivery dashboard.
 */
require_once __DIR__ . '/../../app/bootstrap.php';

$staff = current_staff();
if ($staff && $staff['Role'] === 'Courier') {
	redirect('tracking/courier/deliveries.php');
}
if ($staff && in_array($staff['Role'], ADMIN_ROLES, true)) {
	redirect('tracking/manager/deliveries.php');
}
if ($staff && $staff['Role'] === 'Kitchen Staff') {
	redirect('tracking/kitchen/orders.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$email = trim((string) ($_POST['email'] ?? ''));
	$password = (string) ($_POST['password'] ?? '');
	$attempts = $_SESSION['courier_attempts'] ?? ['n' => 0, 't' => time()];
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
			$_SESSION['courier_attempts'] = $attempts;
			$error = 'Wrong email or password.';
		} elseif ($row['Status'] !== 'Active') {
			$error = 'This staff account is ' . strtolower($row['Status']) . '.';
		} elseif ($row['Role'] !== 'Courier') {
			// Redirect other roles to their dashboards.
			if ($row['Role'] === 'Kitchen Staff') {
				unset($_SESSION['courier_attempts']);
				session_regenerate_id(true);
				$_SESSION['staff_id'] = (int) $row['Staff_ID'];
				q('UPDATE Staff SET Last_login_at = NOW() WHERE Staff_ID = ?', [(int) $row['Staff_ID']]);
				redirect('tracking/kitchen/orders.php');
			}
			if (in_array($row['Role'], ADMIN_ROLES, true)) {
				unset($_SESSION['courier_attempts']);
				session_regenerate_id(true);
				$_SESSION['staff_id'] = (int) $row['Staff_ID'];
				q('UPDATE Staff SET Last_login_at = NOW() WHERE Staff_ID = ?', [(int) $row['Staff_ID']]);
				redirect('admin/overview.php');
			}
			$error = 'This login is for Couriers. Your role: ' . $row['Role'] . '.';
		} else {
			unset($_SESSION['courier_attempts']);
			session_regenerate_id(true);
			$_SESSION['staff_id'] = (int) $row['Staff_ID'];
			q('UPDATE Staff SET Last_login_at = NOW() WHERE Staff_ID = ?', [(int) $row['Staff_ID']]);
			redirect('tracking/courier/deliveries.php');
		}
	}
}

$GLOBALS['__layout_nav'] = 'none';
require_once __DIR__ . '/../../app/layout/master.php';
layout_start(['title' => 'Courier Sign In', 'nav' => 'none', 'body_class' => 'auth', 'styles' => ['pages/auth.css']]);
?>
<link rel="stylesheet" href="<?= e(asset('tracking/assets/css/tracking.css')) ?>">

<div class="track-login-page">
	<div class="track-login-card">
		<div class="track-login-tabs">
			<a class="track-login-tab" href="<?= e(url('tracking/kitchen/login.php')) ?>">Kitchen</a>
			<a class="track-login-tab is-active" href="<?= e(url('tracking/courier/login.php')) ?>">Courier</a>
		</div>
		<h1>Courier Dashboard</h1>
		<p>Sign in to view your assigned deliveries and start GPS tracking.</p>
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
