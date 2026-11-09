<?php
/** Customer accounts — replaces admin/_admin_members.php. Suspend / deactivate / restore; never hard-delete. */
require __DIR__ . '/../app/layout/master.php';
require_staff(ADMIN_ROLES);

layout_start(['title' => 'Members', 'nav' => 'admin', 'active' => 'members', 'styles' => ['pages/admin.css']]);
?>
<div class="page-head" data-reveal>
	<div>
		<span class="eyebrow">Control panel</span>
		<h1>Members</h1>
		<p>Customer accounts. Suspending or deactivating is always reversible, and order history is kept.</p>
	</div>
</div>

<div class="stats">
	<div class="glass stat card-glow" style="--c:var(--ok)" data-reveal><small>Active</small><strong data-count-status="active">–</strong><div class="stat-bar"></div></div>
	<div class="glass stat card-glow" style="--c:var(--warn)" data-reveal><small>Suspended</small><strong data-count-status="suspended">–</strong><div class="stat-bar"></div></div>
	<div class="glass stat card-glow" style="--c:var(--bad)" data-reveal><small>Deactivated</small><strong data-count-status="deactivated">–</strong><div class="stat-bar"></div></div>
</div>

<form class="toolbar glass" id="member-filters" data-reveal onsubmit="return false">
	<label class="search"><?= icon('search') ?><span class="sr-only">Search members</span>
		<input class="input" type="search" name="q" placeholder="Search username, email or phone…" autocomplete="off"><kbd>/</kbd></label>
	<label><span class="sr-only">Status</span>
		<select class="select" name="status"><option value="">Any status</option><option>Active</option><option>Suspended</option><option>Deactivated</option></select></label>
	<label><span class="sr-only">Sort by</span>
		<select class="select" name="sort"><option value="newest">Newest first</option><option value="name">Name A–Z</option><option value="spent">Top spenders</option><option value="orders">Most orders</option><option value="recent">Recently ordered</option></select></label>
	<button type="reset" class="btn btn-ghost btn-sm">Clear</button>
</form>

<div class="glass table-card" data-reveal>
	<div class="table-scroll">
		<table class="table" id="member-table">
			<thead><tr><th>Member</th><th>Status</th><th>Tier</th><th>Orders</th><th>Spent</th><th>Last order</th><th>Joined</th><th><span class="sr-only">Actions</span></th></tr></thead>
			<tbody><?php for ($i = 0; $i < 4; $i++): ?><tr><td colspan="8"><div class="skeleton" style="height:28px"></div></td></tr><?php endfor; ?></tbody>
		</table>
	</div>
	<div class="table-foot">
		<span id="member-summary">Loading…</span>
		<div class="pager">
			<button class="btn btn-sm btn-glass" data-page="prev" aria-label="Previous page">←</button>
			<button class="btn btn-sm btn-glass" data-page="next" aria-label="Next page">→</button>
		</div>
	</div>
</div>
<?php layout_end(['pages/members.js']);
