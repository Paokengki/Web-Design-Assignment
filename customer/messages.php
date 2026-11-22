<?php
/**
 * Customer Messages — every private order chat (restaurant / driver / CafeDash support) in one inbox.
 * Opened from the chat button in the navbar. Each chat also appears on its order's Track Order page.
 * Data: tracking/api/chat.php (as=customer), polled every 2 s by tracking/assets/js/staff-messages.js.
 */
require __DIR__ . '/../app/layout/master.php';

require_customer();

layout_start(['title' => 'Messages', 'nav' => 'shop', 'active' => 'messages', 'styles' => ['pages/shop.css', 'tracking/assets/css/tracking.css']]);
?>
<div class="page-head" data-reveal>
	<div>
		<span class="eyebrow">Order chats</span>
		<h1>Messages</h1>
		<p>Private chats with cafés, your drivers and CafeDash support. To start one, open an order’s <a class="grad-text" href="<?= e(url('customer/orders.php')) ?>"><b>Track order</b></a> page.</p>
	</div>
</div>

<div class="inbox" id="inbox" data-as="customer">
	<div class="inbox-list glass">
		<div class="inbox-threads" id="inbox-threads"><p class="inbox-empty">Loading…</p></div>
	</div>
	<div class="inbox-pane glass">
		<div class="inbox-pane-head">
			<h3 id="inbox-title">Pick a conversation</h3>
			<small id="inbox-sub"></small>
		</div>
		<div class="inbox-log" id="inbox-log" role="log" aria-live="polite"><p class="inbox-empty">Your conversations about orders show up here.</p></div>
		<form class="inbox-form" id="inbox-form" autocomplete="off">
			<input type="text" name="body" maxlength="1000" placeholder="Select a conversation" aria-label="Message" disabled>
			<button class="btn btn-primary btn-sm" type="submit" disabled>Send</button>
		</form>
	</div>
</div>
<?php layout_end(['tracking/assets/js/staff-messages.js']);
