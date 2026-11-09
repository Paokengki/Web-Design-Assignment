<?php
/** Module 3 — Admin User & Staff Management. Super Admin only. */
require __DIR__ . '/../app/layout/master.php';
$me = require_staff(['Super Admin']);

layout_start(['title' => 'Staff', 'nav' => 'admin', 'active' => 'staff']);
?>
<div class="page-head" data-reveal>
	<div>
		<span class="eyebrow">Control panel</span>
		<h1>Staff &amp; roles</h1>
		<p>Assign roles, reset passwords and suspend access. Changes apply instantly.</p>
	</div>
	<div class="page-head-actions">
		<button class="btn btn-primary" data-magnetic data-staff-new>
			<svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>Add staff
		</button>
	</div>
</div>

<div class="stats">
	<div class="glass stat card-glow" style="--c:var(--role-super)" data-reveal><small>Super Admins</small><strong data-role-count="Super Admin">–</strong><div class="stat-bar"></div></div>
	<div class="glass stat card-glow" style="--c:var(--role-manager)" data-reveal><small>Managers</small><strong data-role-count="Manager">–</strong><div class="stat-bar"></div></div>
	<div class="glass stat card-glow" style="--c:var(--role-kitchen)" data-reveal><small>Kitchen Staff</small><strong data-role-count="Kitchen Staff">–</strong><div class="stat-bar"></div></div>
	<div class="glass stat card-glow" style="--c:var(--role-courier)" data-reveal><small>Couriers</small><strong data-role-count="Courier">–</strong><div class="stat-bar"></div></div>
</div>

<form class="toolbar glass" id="staff-filters" data-reveal onsubmit="return false">
	<label class="search">
		<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
		<span class="sr-only">Search staff</span>
		<input class="input" type="search" name="q" placeholder="Search name, email, phone or plate…" autocomplete="off">
		<kbd>/</kbd>
	</label>
	<label><span class="sr-only">Role</span>
		<select class="select" name="role">
			<option value="">All roles</option>
			<?php foreach (STAFF_ROLES as $r): ?><option><?= e($r) ?></option><?php endforeach; ?>
		</select>
	</label>
	<label><span class="sr-only">Status</span>
		<select class="select" name="status">
			<option value="">Any status</option><option>Active</option><option>Suspended</option><option>Deactivated</option>
		</select>
	</label>
	<label><span class="sr-only">Sort by</span>
		<select class="select" name="sort">
			<option value="newest">Newest first</option>
			<option value="oldest">Oldest first</option>
			<option value="name">Name A–Z</option>
			<option value="name_za">Name Z–A</option>
			<option value="role">Role</option>
			<option value="login">Last sign-in</option>
		</select>
	</label>
	<label title="Created from"><span class="sr-only">Created from</span><input class="input" type="date" name="from"></label>
	<label title="Created to"><span class="sr-only">Created to</span><input class="input" type="date" name="to"></label>
	<button type="reset" class="btn btn-ghost btn-sm">Clear</button>
</form>

<div class="glass table-card" data-reveal>
	<div class="table-scroll">
		<table class="table" id="staff-table">
			<thead><tr>
				<th>Staff member</th><th>Role</th><th>Status</th><th>Phone</th><th>Created</th><th>Last sign-in</th><th><span class="sr-only">Actions</span></th>
			</tr></thead>
			<tbody>
				<?php for ($i = 0; $i < 4; $i++): ?><tr><td colspan="7"><div class="skeleton" style="height:28px"></div></td></tr><?php endfor; ?>
			</tbody>
		</table>
	</div>
	<div class="table-foot">
		<span id="staff-summary">Loading…</span>
		<div class="pager">
			<button class="btn btn-sm btn-glass" data-page="prev" aria-label="Previous page">←</button>
			<button class="btn btn-sm btn-glass" data-page="next" aria-label="Next page">→</button>
		</div>
	</div>
</div>

<!-- Add / edit staff -->
<div class="modal" id="staff-modal">
	<div class="modal-backdrop"></div>
	<div class="modal-panel wide" role="dialog" aria-modal="true" aria-labelledby="staff-title">
		<div class="modal-head">
			<div><h2 id="staff-title">Add staff</h2><p id="staff-sub">They sign in with their work email.</p></div>
			<button class="icon-btn" data-close-modal aria-label="Close"><svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
		</div>
		<form id="staff-form" novalidate>
			<input type="hidden" name="action" value="create">
			<input type="hidden" name="Staff_ID" value="">
			<div class="form-grid">
				<label class="field"><span>Full name</span><input class="input" name="Name" required autocomplete="off"><span class="field-error"></span></label>
				<label class="field"><span>Work email</span><input class="input" type="email" name="Email" required autocomplete="off"><span class="field-error"></span></label>
				<label class="field"><span>Phone</span><input class="input" type="tel" name="Phone" autocomplete="off"><span class="field-error"></span></label>
				<label class="field" data-create-only><span>Role</span>
					<select class="select" name="Role">
						<?php foreach (ASSIGNABLE_ROLES as $r): ?><option><?= e($r) ?></option><?php endforeach; ?>
					</select><span class="field-error"></span>
				</label>
				<label class="field" data-courier-only><span>Vehicle plate</span><input class="input" name="Plate_number" style="text-transform:uppercase" placeholder="WXY 1234"><span class="field-error"></span></label>
				<label class="field span-2" data-create-only><span>Initial password <em class="tiny">(leave empty to generate one)</em></span>
					<input class="input" type="password" name="Password" autocomplete="new-password" data-strength="#staff-strength">
					<span class="field-error"></span>
					<div id="staff-strength"></div>
				</label>
			</div>
			<div class="form-actions">
				<button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
				<button type="submit" class="btn btn-primary">Save</button>
			</div>
		</form>
	</div>
</div>

<!-- Temporary password reveal -->
<div class="modal" id="secret-modal">
	<div class="modal-backdrop"></div>
	<div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="secret-title">
		<div class="modal-head">
			<div><h2 id="secret-title">Temporary password</h2><p id="secret-sub"></p></div>
		</div>
		<div class="secret-box"><span id="secret-value"></span><button class="icon-btn" id="secret-copy" aria-label="Copy password"><svg viewBox="0 0 24 24"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg></button></div>
		<p class="tiny" style="margin-top:12px">This is shown once. Share it privately; only a hash is stored.</p>
		<div class="form-actions"><button class="btn btn-primary" data-close-modal>Done</button></div>
	</div>
</div>

<?php layout_end(['pages/staff.js']);
