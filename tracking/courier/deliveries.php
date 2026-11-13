<?php
/**
 * Courier delivery dashboard.
 *
 * Shows deliveries assigned to the logged-in courier.
 * Features:
 *   - List of assigned deliveries with pickup/dropoff details
 *   - GPS toggle button (browser Geolocation API)
 *   - Status updates: Assigned → Out for Delivery → Delivered
 *   - Auto-refresh every 3 seconds
 *
 * Uses existing Staff session ($_SESSION['staff_id']).
 */
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../config.php';

$staff = current_staff();
if (!$staff) redirect('tracking/courier/login.php');
if ($staff['Role'] !== 'Courier') {
	if (in_array($staff['Role'], ADMIN_ROLES, true)) redirect('tracking/manager/deliveries.php');
	if ($staff['Role'] === 'Kitchen Staff') redirect('tracking/kitchen/orders.php');
	redirect('tracking/courier/login.php');
}

$GLOBALS['__layout_nav'] = 'admin';
require_once __DIR__ . '/../../app/layout/master.php';
layout_start([
	'title' => 'Courier Deliveries',
	'nav' => 'admin',
	'active' => 'courier',
	'styles' => ['pages/admin.css'],
]);
?>
<link rel="stylesheet" href="<?= e(asset('tracking/assets/css/tracking.css')) ?>">

<div class="courier-page">
	<div class="page-head" data-reveal>
		<div>
			<span class="eyebrow">Courier Dashboard</span>
			<h1>Your Deliveries</h1>
			<p>Assigned deliveries will appear here. Turn on GPS to share your location with customers.</p>
		</div>
		<div style="display:flex;gap:8px;align-items:center;">
			<button class="btn btn-glass btn-sm" id="gps-toggle">Start GPS</button>
			<form method="post" action="<?= e(url('auth/logout.php')) ?>">
				<?= csrf_field() ?><input type="hidden" name="who" value="staff">
				<button class="btn btn-glass btn-sm" type="submit">Sign out</button>
			</form>
		</div>
	</div>

	<div class="courier-gps-banner" id="courier-gps-banner">
		GPS is off. Turn it on to share your location with customers.
	</div>

	<div id="courier-deliveries">
		<div class="glass" style="padding:40px;text-align:center;border-radius:16px;">
			<p style="font-size:16px;">Loading...</p>
		</div>
	</div>
</div>
<?php layout_end(['tracking/assets/js/courier-deliveries.js']);
