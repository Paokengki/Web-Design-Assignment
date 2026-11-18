<?php
/** Café registration approvals — staff review new café applications. Place in admin/. */
require __DIR__ . '/../app/layout/master.php';
require_staff(ADMIN_ROLES);

layout_start(['title' => 'Café applications', 'nav' => 'admin', 'active' => 'applications', 'styles' => ['pages/admin.css']]);
?>
<div class="page-head" data-reveal>
	<div>
		<span class="eyebrow">Control panel</span>
		<h1>Café applications</h1>
		<p>Review new café registrations. Approving creates the café and a kitchen staff account, then emails the owner their sign-in details.</p>
	</div>
</div>

<div class="stats">
	<div class="glass stat card-glow" style="--c:var(--warn)" data-reveal><small>Pending</small><strong data-count-status="Pending">–</strong><div class="stat-bar"></div></div>
	<div class="glass stat card-glow" style="--c:var(--ok)" data-reveal><small>Approved</small><strong data-count-status="Approved">–</strong><div class="stat-bar"></div></div>
	<div class="glass stat card-glow" style="--c:var(--bad)" data-reveal><small>Rejected</small><strong data-count-status="Rejected">–</strong><div class="stat-bar"></div></div>
</div>

<form class="toolbar glass" id="app-filters" data-reveal onsubmit="return false">
	<label class="search"><?= icon('search') ?><span class="sr-only">Search applications</span>
		<input class="input" type="search" name="q" placeholder="Search café, owner or email…" autocomplete="off"><kbd>/</kbd></label>
	<label><span class="sr-only">Status</span>
		<select class="select" name="status"><option>Pending</option><option>Approved</option><option>Rejected</option><option value="">Any status</option></select></label>
	<button type="reset" class="btn btn-ghost btn-sm">Clear</button>
</form>

<div class="glass table-card" data-reveal>
	<div class="table-scroll">
		<table class="table" id="app-table">
			<thead><tr><th>Café</th><th>Owner</th><th>Contact</th><th>Address</th><th>Applied</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
			<tbody><?php for ($i = 0; $i < 4; $i++): ?><tr><td colspan="7"><div class="skeleton" style="height:28px"></div></td></tr><?php endfor; ?></tbody>
		</table>
	</div>
	<div class="table-foot">
		<span id="app-summary">Loading…</span>
		<div class="pager">
			<button class="btn btn-sm btn-glass" data-page="prev" aria-label="Previous page">←</button>
			<button class="btn btn-sm btn-glass" data-page="next" aria-label="Next page">→</button>
		</div>
	</div>
</div>

<!-- Reject dialog -->
<div class="modal" id="reject-modal">
	<div class="modal-backdrop"></div>
	<div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="reject-title">
		<div class="modal-head">
			<div><h2 id="reject-title">Reject application</h2><p id="reject-sub"></p></div>
			<button class="icon-btn" data-close-modal aria-label="Close"><?= icon('x') ?></button>
		</div>
		<form id="reject-form" novalidate>
			<input type="hidden" name="action" value="reject">
			<input type="hidden" name="Application_ID" value="">
			<label class="field"><span>Reason <em class="tiny">(optional)</em></span>
				<textarea class="textarea" name="Reject_reason" rows="3" maxlength="255"></textarea><span class="field-error"></span></label>
			<div class="form-actions">
				<button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
				<button type="submit" class="btn btn-primary">Reject</button>
			</div>
		</form>
	</div>
</div>

<!-- Temporary password reveal (shown once after approval) -->
<div class="modal" id="secret-modal">
	<div class="modal-backdrop"></div>
	<div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="secret-title">
		<div class="modal-head"><div><h2 id="secret-title">Café approved</h2><p id="secret-sub"></p></div></div>
		<div class="secret-box"><span id="secret-value"></span><button class="icon-btn" id="secret-copy" aria-label="Copy password"><?= icon('copy') ?></button></div>
		<p class="tiny" style="margin-top:12px">This temporary password is shown once. Share it privately with the owner.</p>
		<div class="form-actions"><button class="btn btn-primary" data-close-modal>Done</button></div>
	</div>
</div>
<?php layout_end(['pages/applications.js']);
