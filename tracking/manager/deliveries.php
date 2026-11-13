<?php
/**
 * Manager delivery assignment dashboard.
 *
 * Managers and Super Admins can:
 *   - View orders that are Ready for Pickup
 *   - Assign an available Courier to each order
 *   - Monitor active deliveries (Assigned / Out for Delivery)
 *
 * This page uses the existing manager auth (require_staff with ADMIN_ROLES).
 * It integrates into the existing admin layout as a sidebar nav item.
 *
 * Accessible from the admin sidebar "Live Orders" link.
 */
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../config.php';

$staff = current_staff();
if (!$staff) redirect('auth/staff_login.php');
if (!in_array($staff['Role'], ADMIN_ROLES, true)) {
	redirect('tracking/kitchen/login.php');
}

$GLOBALS['__layout_nav'] = 'admin';
require_once __DIR__ . '/../../app/layout/master.php';
layout_start([
	'title' => 'Live Orders & Delivery',
	'nav' => 'admin',
	'active' => 'tracking',
	'styles' => ['pages/admin.css'],
]);
?>
<link rel="stylesheet" href="<?= e(asset('tracking/assets/css/tracking.css')) ?>">

<div class="manager-page">
	<div class="page-head" data-reveal>
		<div>
			<span class="eyebrow">Delivery Management</span>
			<h1>Live Orders &amp; Delivery</h1>
			<p>Assign couriers to ready orders and track active deliveries in real time.</p>
		</div>
	</div>

	<div class="manager-tabs glass" data-reveal>
		<button class="manager-tab is-active" data-tab="ready">Ready for Pickup</button>
		<button class="manager-tab" data-tab="active">Active Deliveries</button>
	</div>

	<!-- Ready for Pickup tab -->
	<div id="manager-ready">
		<div id="ready-list">
			<div class="glass" style="padding:40px;text-align:center;border-radius:16px;">
				<p style="font-size:16px;">Loading...</p>
			</div>
		</div>
	</div>

	<!-- Active deliveries tab -->
	<div id="manager-active" style="display:none;">
		<div id="active-list">
			<div class="glass" style="padding:40px;text-align:center;border-radius:16px;">
				<p style="font-size:16px;">Loading...</p>
			</div>
		</div>
	</div>
</div>
<?php layout_end(['tracking/assets/js/manager-deliveries.js']);
