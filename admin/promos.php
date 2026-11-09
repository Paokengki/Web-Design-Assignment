<?php
/** Module 2 — Promotional Voucher & Discounts Management. */
require __DIR__ . '/../app/layout/master.php';
$staff = require_staff(ADMIN_ROLES);

layout_start(['title' => 'Promotions', 'nav' => 'admin', 'active' => 'promos']);
?>
<div class="page-head" data-reveal>
	<div>
		<span class="eyebrow">Control panel</span>
		<h1>Promotions</h1>
		<p>Create voucher codes, schedule campaigns and pull a code the moment you need to.</p>
	</div>
	<div class="page-head-actions">
		<button class="btn btn-primary" data-magnetic data-promo-new>
			<svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>New promo
		</button>
	</div>
</div>

<div class="stats" id="promo-stats">
	<div class="glass stat card-glow" style="--c:var(--ok)" data-reveal><small>Active</small><strong data-stat="active">–</strong><div class="stat-bar"></div></div>
	<div class="glass stat card-glow" style="--c:var(--info)" data-reveal><small>Scheduled</small><strong data-stat="scheduled">–</strong><div class="stat-bar"></div></div>
	<div class="glass stat card-glow" style="--c:var(--muted)" data-reveal><small>Expired</small><strong data-stat="expired">–</strong><div class="stat-bar"></div></div>
	<div class="glass stat card-glow" style="--c:var(--amber)" data-reveal><small>Total redemptions</small><strong data-stat="redemptions">–</strong><div class="stat-bar"></div></div>
</div>

<div class="toolbar glass" data-reveal>
	<label class="search">
		<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
		<span class="sr-only">Search promotions</span>
		<input class="input" type="search" id="promo-search" placeholder="Search code or title…" autocomplete="off">
		<kbd>/</kbd>
	</label>
	<label class="filter-chip"><input type="checkbox" id="promo-show-paused" checked> Show paused</label>
	<div class="seg" role="tablist" aria-label="View">
		<button type="button" class="is-active" data-view="board" role="tab" aria-selected="true"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="5" height="16" rx="1"/><rect x="10" y="4" width="5" height="10" rx="1"/><rect x="17" y="4" width="4" height="13" rx="1"/></svg>Board</button>
		<button type="button" data-view="grid" role="tab" aria-selected="false"><svg viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"/></svg>Grid</button>
	</div>
</div>

<!-- Board view -->
<div class="board" id="promo-board">
	<?php foreach (['active' => 'Active', 'scheduled' => 'Scheduled', 'expired' => 'Expired'] as $key => $label): ?>
		<section class="column col-<?= $key ?>" data-col="<?= $key ?>">
			<header class="column-head"><h3><i></i><?= $label ?></h3><span class="count" data-count-col="<?= $key ?>">0</span></header>
			<div class="column-body"><div class="skeleton" style="height:180px"></div></div>
		</section>
	<?php endforeach; ?>
</div>

<!-- Grid view -->
<div class="glass table-card" id="promo-grid" hidden>
	<div class="table-scroll">
		<table class="table">
			<thead><tr>
				<th>Code</th><th>Campaign</th><th>Discount</th><th>Usage</th><th>Window</th><th>Status</th><th><span class="sr-only">Actions</span></th>
			</tr></thead>
			<tbody></tbody>
		</table>
	</div>
</div>

<!-- Create / edit form -->
<div class="modal" id="promo-modal">
	<div class="modal-backdrop"></div>
	<div class="modal-panel wide" role="dialog" aria-modal="true" aria-labelledby="promo-title">
		<div class="modal-head">
			<div><h2 id="promo-title">New promotion</h2><p>Customers type the code at checkout.</p></div>
			<button class="icon-btn" data-close-modal aria-label="Close"><svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
		</div>
		<form id="promo-form" novalidate>
			<input type="hidden" name="action" value="create">
			<input type="hidden" name="Promo_ID" value="">
			<div class="form-grid">
				<label class="field"><span>Promo code</span>
					<span class="input-wrap">
						<input class="input mono" name="Code" maxlength="20" required style="text-transform:uppercase" placeholder="LATTE20">
						<button type="button" class="icon-btn" id="promo-generate" title="Generate a code" aria-label="Generate a code"><svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/></svg></button>
					</span>
					<span class="field-error"></span>
				</label>
				<label class="field"><span>Title</span><input class="input" name="Title" maxlength="120" required placeholder="Latte Tuesday"><span class="field-error"></span></label>
				<label class="field span-2"><span>Description <em class="tiny">(shown to customers)</em></span><input class="input" name="Description" maxlength="255"><span class="field-error"></span></label>
				<label class="field"><span>Discount %</span><input class="input" name="Discount_percent" type="number" min="1" max="100" step="0.5" required><span class="field-error"></span></label>
				<label class="field"><span>Max redemptions <em class="tiny">(empty = unlimited)</em></span><input class="input" name="Max_redemptions" type="number" min="1" step="1"><span class="field-error"></span></label>
				<label class="field"><span>Minimum spend (RM)</span><input class="input" name="Min_spend" type="number" min="0" step="0.01" value="0"><span class="field-error"></span></label>
				<div></div>
				<label class="field"><span>Starts</span><input class="input" name="Starts_at" type="datetime-local" required><span class="field-error"></span></label>
				<label class="field"><span>Expires</span><input class="input" name="Expires_at" type="datetime-local" required><span class="field-error"></span></label>
			</div>
			<div class="form-actions">
				<button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
				<button type="submit" class="btn btn-primary">Save promotion</button>
			</div>
		</form>
	</div>
</div>

<?php layout_end(['pages/promos.js']);
