<?php
/**
 * Kitchen Staff order queue dashboard.
 *
 * Shows orders for the logged-in Kitchen Staff member's assigned restaurant.
 * Multi-café orders: only this café's items are shown, but the order appears in the queue.
 *
 * Auto-refreshes every 4 seconds via AJAX.
 * Kitchen staff can: Accept → Preparing → Ready for Pickup.
 */
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../config.php';

$staff = current_staff();
if (!$staff) redirect('tracking/kitchen/login.php');
if ($staff['Role'] !== 'Kitchen Staff') {
	// Redirect to appropriate dashboard.
	if (in_array($staff['Role'], ADMIN_ROLES, true)) redirect('tracking/manager/deliveries.php');
	if ($staff['Role'] === 'Courier') redirect('tracking/courier/deliveries.php');
	redirect('tracking/kitchen/login.php');
}

$assignedRest = (int) ($staff['Assigned_Restaurant_ID'] ?? 0);
$restaurantName = '';
if ($assignedRest > 0) {
	$rest = q_one('SELECT Name FROM Restaurant WHERE Restaurant_ID = ?', [$assignedRest]);
	$restaurantName = $rest ? $rest['Name'] : '';
}

$GLOBALS['__layout_nav'] = 'admin';
require_once __DIR__ . '/../../app/layout/master.php';
layout_start([
	'title' => 'Kitchen Orders',
	'nav' => 'admin',
	'active' => 'kitchen',
	'styles' => ['pages/admin.css'],
]);
?>
<link rel="stylesheet" href="<?= e(asset('tracking/assets/css/tracking.css')) ?>">

<div class="kitchen-page">
	<div class="page-head" data-reveal>
		<div>
			<span class="eyebrow">Kitchen Dashboard</span>
			<h1><?= e($restaurantName ?: 'Unassigned') ?> Orders</h1>
			<p>Active orders for your caf&eacute;. New orders appear automatically.</p>
		</div>
		<form method="post" action="<?= e(url('auth/logout.php')) ?>">
			<?= csrf_field() ?><input type="hidden" name="who" value="staff">
			<button class="btn btn-glass btn-sm" type="submit">Sign out</button>
		</form>
	</div>

	<?php if ($assignedRest <= 0): ?>
		<div class="flash flash-error" data-reveal>
			<strong>You are not assigned to a caf&eacute;.</strong>
			Contact your manager to be assigned to a restaurant before you can receive orders.
		</div>
	<?php else: ?>
		<div class="kitchen-stats" data-reveal>
			<div class="glass kitchen-stat"><small>Pending</small><strong id="stat-pending">-</strong></div>
			<div class="glass kitchen-stat"><small>Accepted</small><strong id="stat-accepted">-</strong></div>
			<div class="glass kitchen-stat"><small>Preparing</small><strong id="stat-preparing">-</strong></div>
			<div class="glass kitchen-stat"><small>Ready</small><strong id="stat-ready">-</strong></div>
		</div>

		<div class="kitchen-orders" id="kitchen-orders">
			<div class="glass" style="padding:40px;text-align:center;border-radius:16px;">
				<p style="font-size:16px;">Loading orders...</p>
			</div>
		</div>
	<?php endif; ?>
</div>

<script>
	// Stats updater — counts from the rendered order cards.
	function updateStats() {
		const cards = document.querySelectorAll('.kitchen-order-card');
		const counts = { Pending: 0, Accepted: 0, Preparing: 0, 'Ready for Pickup': 0 };
		cards.forEach(c => {
			const pill = c.querySelector('.track-pill');
			if (!pill) return;
			const text = pill.textContent.trim();
			Object.keys(counts).forEach(k => {
				if (text.includes(k)) counts[k]++;
			});
		});
		const setVal = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
		setVal('stat-pending', counts['Pending']);
		setVal('stat-accepted', counts['Accepted']);
		setVal('stat-preparing', counts['Preparing']);
		setVal('stat-ready', counts['Ready for Pickup']);
	}
	// Run after each poll completes.
	const observer = new MutationObserver(() => updateStats());
	const list = document.getElementById('kitchen-orders');
	if (list) observer.observe(list, { childList: true, subtree: true });
</script>
<?php layout_end(['tracking/assets/js/kitchen-orders.js']);
