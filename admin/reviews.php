<?php
/** Reviews & ratings — search / filter, reply, issue status, hide (soft delete). Place in admin/. */
require __DIR__ . '/../app/layout/master.php';
require_staff(ADMIN_ROLES);

layout_start(['title' => 'Reviews & ratings', 'nav' => 'admin', 'active' => 'reviews', 'styles' => ['pages/admin.css', 'pages/reviews.css']]);
?>
<div class="page-head" data-reveal>
	<div>
		<span class="eyebrow">Control panel</span>
		<h1>Reviews &amp; ratings</h1>
		<p>Customer feedback on delivered orders. Reply, track the issue, or hide inappropriate reviews — hidden reviews can always be restored.</p>
	</div>
</div>

<div class="stats">
	<div class="glass stat card-glow" style="--c:var(--amber)" data-reveal><small>Average rating</small><strong data-stat="avg_rating">–</strong><div class="stat-bar"></div></div>
	<div class="glass stat card-glow" style="--c:var(--warn)" data-reveal><small>Pending</small><strong data-stat="pending">–</strong><div class="stat-bar"></div></div>
	<div class="glass stat card-glow" style="--c:var(--info)" data-reveal><small>Investigating</small><strong data-stat="investigating">–</strong><div class="stat-bar"></div></div>
	<div class="glass stat card-glow" style="--c:var(--ok)" data-reveal><small>Resolved</small><strong data-stat="resolved">–</strong><div class="stat-bar"></div></div>
</div>

<form class="toolbar glass" id="rv-filters" data-reveal onsubmit="return false">
	<label class="search"><?= icon('search') ?><span class="sr-only">Search reviews</span>
		<input class="input" type="search" name="q" placeholder="Search customer name, email or text…" autocomplete="off"><kbd>/</kbd></label>
	<label><span class="sr-only">Rating</span>
		<select class="select" name="rating"><option value="">Any rating</option><option value="5">5 stars</option><option value="4">4 stars</option><option value="3">3 stars</option><option value="2">2 stars</option><option value="1">1 star</option></select></label>
	<label><span class="sr-only">Issue status</span>
		<select class="select" name="status"><option value="">Any status</option><option>Pending</option><option>Investigating</option><option>Resolved</option></select></label>
	<label><span class="sr-only">Visibility</span>
		<select class="select" name="visibility"><option value="">Visible + hidden</option><option value="visible">Visible only</option><option value="hidden">Hidden only</option></select></label>
	<label><span class="sr-only">Café</span>
		<select class="select" name="cafe"><option value="">All cafés</option></select></label>
	<label title="Posted from"><span class="sr-only">Posted from</span><input class="input" type="date" name="from"></label>
	<label title="Posted to"><span class="sr-only">Posted to</span><input class="input" type="date" name="to"></label>
	<label><span class="sr-only">Sort by</span>
		<select class="select" name="sort"><option value="newest">Newest first</option><option value="oldest">Oldest first</option><option value="lowest">Lowest rating</option><option value="highest">Highest rating</option></select></label>
	<button type="reset" class="btn btn-ghost btn-sm">Clear</button>
</form>

<div class="glass table-card" data-reveal>
	<div class="table-scroll">
		<table class="table" id="rv-table">
			<thead><tr><th>Posted</th><th>Customer</th><th>Café</th><th>Rating</th><th>Review</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
			<tbody><?php for ($i = 0; $i < 4; $i++): ?><tr><td colspan="7"><div class="skeleton" style="height:28px"></div></td></tr><?php endfor; ?></tbody>
		</table>
	</div>
	<div class="table-foot">
		<span id="rv-summary">Loading…</span>
		<div class="pager">
			<button class="btn btn-sm btn-glass" data-page="prev" aria-label="Previous page">←</button>
			<button class="btn btn-sm btn-glass" data-page="next" aria-label="Next page">→</button>
		</div>
	</div>
</div>

<!-- Reply + status -->
<div class="modal" id="reply-modal">
	<div class="modal-backdrop"></div>
	<div class="modal-panel wide" role="dialog" aria-modal="true" aria-labelledby="reply-title">
		<div class="modal-head">
			<div><h2 id="reply-title">Reply to review</h2><p>The customer sees your reply under their review.</p></div>
			<button class="icon-btn" data-close-modal aria-label="Close"><?= icon('x') ?></button>
		</div>
		<div class="rv-preview" id="rv-preview"></div>
		<form id="reply-form" novalidate>
			<input type="hidden" name="action" value="reply">
			<input type="hidden" name="Review_ID" value="">
			<div class="form-grid">
				<label class="field span-2"><span>Official reply</span>
					<textarea class="textarea" name="Admin_reply" rows="4" maxlength="1000" required></textarea><span class="field-error"></span></label>
				<label class="field"><span>Issue status</span>
					<select class="select" name="Issue_status"><option>Pending</option><option>Investigating</option><option>Resolved</option></select><span class="field-error"></span></label>
			</div>
			<div class="form-actions">
				<button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
				<button type="submit" class="btn btn-primary">Save reply</button>
			</div>
		</form>
	</div>
</div>
<?php layout_end(['pages/admin-reviews.js']);
