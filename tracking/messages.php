<?php
/**
 * Staff Messages inbox — reply to customers' private order chats.
 *
 *   Kitchen Staff : restaurant chats of their café
 *   Courier       : chats of orders they deliver
 *   Manager / Super Admin : support chats (reply) + every other chat (read-only)
 *
 * Data: tracking/api/chat.php (as=staff), polled every 2 s.
 */
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/config.php';

$staff = require_staff(STAFF_ROLES);
$isAdmin = in_array($staff['Role'], ADMIN_ROLES, true);
$scope = match (true) {
	$isAdmin => 'Support chats you answer, plus every restaurant and driver chat (read-only).',
	$staff['Role'] === 'Kitchen Staff' => 'Customers chatting with your café about their orders.',
	default => 'Customers whose orders you deliver.',
};

$GLOBALS['__layout_nav'] = 'admin';
require_once __DIR__ . '/../app/layout/master.php';
layout_start([
	'title' => 'Messages',
	'nav' => 'admin',
	'active' => 'messages',
	'styles' => ['pages/admin.css'],
]);
?>
<link rel="stylesheet" href="<?= e(asset('tracking/assets/css/tracking.css')) ?>">

<div class="page-head" data-reveal>
	<div>
		<span class="eyebrow">Order chats</span>
		<h1>Messages</h1>
		<p><?= e($scope) ?></p>
	</div>
</div>

<div class="inbox" id="inbox" data-as="staff" data-admin="<?= $isAdmin ? '1' : '0' ?>">
	<div class="inbox-list glass">
		<?php if ($isAdmin): ?>
		<div class="inbox-filter order-chat-tabs" role="tablist" style="margin-top:0">
			<button type="button" class="is-active" data-filter="">All</button>
			<button type="button" data-filter="admin">Support</button>
			<button type="button" data-filter="restaurant">Restaurant</button>
			<button type="button" data-filter="driver">Driver</button>
		</div>
		<?php endif; ?>
		<div class="inbox-threads" id="inbox-threads"><p class="inbox-empty">Loading…</p></div>
	</div>
	<div class="inbox-pane glass">
		<div class="inbox-pane-head">
			<h3 id="inbox-title">Pick a conversation</h3>
			<small id="inbox-sub"></small>
		</div>
		<div class="inbox-log" id="inbox-log" role="log" aria-live="polite"><p class="inbox-empty">Conversations appear here when a customer writes or an order sends an update.</p></div>
		<form class="inbox-form" id="inbox-form" autocomplete="off">
			<input type="text" name="body" maxlength="1000" placeholder="Select a conversation" aria-label="Reply" disabled>
			<button class="btn btn-primary btn-sm" type="submit" disabled>Send</button>
		</form>
	</div>
</div>

<?php layout_end(['tracking/assets/js/staff-messages.js']);
