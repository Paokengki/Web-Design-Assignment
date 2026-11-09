<?php
/** Feedback inbox — replaces admin/_admin_feedback.php + admin_panel.php. Messages from the contact form. */
require __DIR__ . '/../app/layout/master.php';
require_staff(ADMIN_ROLES);

layout_start(['title' => 'Feedback', 'nav' => 'admin', 'active' => 'feedback', 'styles' => ['pages/admin.css']]);
?>
<div class="page-head" data-reveal>
	<div>
		<span class="eyebrow">Control panel</span>
		<h1>Feedback <span class="pill pill-amber" id="unread-pill" hidden></span></h1>
		<p>Messages sent through the customer Feedback page.</p>
	</div>
</div>

<div class="inbox">
	<aside class="glass card inbox-list" data-reveal>
		<div class="inbox-tools">
			<label class="search search-sm"><?= icon('search') ?><span class="sr-only">Search messages</span><input class="input" type="search" id="fb-q" placeholder="Search…" autocomplete="off"></label>
			<div class="seg" role="radiogroup" aria-label="Filter">
				<button type="button" class="is-active" data-filter="" role="radio" aria-checked="true">All</button>
				<button type="button" data-filter="unread" role="radio" aria-checked="false">Unread</button>
			</div>
		</div>
		<div id="fb-items" class="inbox-items" role="listbox" aria-label="Messages"></div>
	</aside>
	<section class="glass card inbox-read" id="fb-read" aria-live="polite" data-reveal>
		<div class="empty-detail"><?= icon('inbox') ?><p>Select a message to read it.</p></div>
	</section>
</div>
<?php layout_end(['pages/feedback.js']);
