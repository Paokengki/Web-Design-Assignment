<?php
/** Module 1 — Customer Profile & Loyalty dashboard. */
require __DIR__ . '/../app/layout/master.php';
require __DIR__ . '/../app/loyalty.php';

$user = require_customer();
$uid = (int) $user['User_ID'];

$totals = q_one("SELECT COUNT(*) AS orders, COALESCE(SUM(Payment_amount), 0) AS spent
	FROM Payment WHERE User_ID = ? AND Payment_status = 'SUCCEEDED'", [$uid]);
$loyalty = loyalty_for((float) $totals['spent']);

$recent = q_all("SELECT p.Payment_ID, p.Payment_amount, p.Payment_status, p.Created_at, r.Name AS cafe
	FROM Payment p LEFT JOIN Restaurant r ON r.Restaurant_ID = p.Restaurant_ID
	WHERE p.User_ID = ? ORDER BY p.Created_at DESC LIMIT 5", [$uid]);

$addresses = q_all('SELECT * FROM User_Address WHERE User_ID = ? ORDER BY Is_default DESC, Created_at DESC', [$uid]);

$avatar = $user['Profile_Image'] ? url(preg_replace('#^(\.\./)+#', '', $user['Profile_Image'])) : null;
$statusPill = ['SUCCEEDED' => 'pill-ok', 'PENDING' => 'pill-warn', 'FAILED' => 'pill-bad', 'REFUNDED' => 'pill-muted'];

layout_start(['title' => 'Dashboard', 'nav' => 'shop', 'active' => 'dashboard', 'styles' => ['pages/dashboard.css']]);
?>
<div class="page-head" data-reveal>
	<div>
		<span class="eyebrow">Member dashboard</span>
		<h1>Hey, <?= e($user['User_Name']) ?>.</h1>
		<p>Your points, addresses and account in one place.</p>
	</div>
	<div class="page-head-actions">
		<a class="btn btn-primary" data-magnetic href="<?= e(url('customer/home.php')) ?>">Order now</a>
	</div>
</div>

<div class="dash-grid">
	<!-- ============ LEFT: profile + loyalty ============ -->
	<div class="dash-col">
		<section class="glass card profile-card card-glow" data-reveal>
			<div class="profile-top">
				<label class="avatar-upload" title="Change photo">
					<span class="avatar avatar-lg" id="avatar-preview"><?php if ($avatar): ?><img src="<?= e($avatar) ?>" alt=""><?php else: ?><?= e(initials($user['User_Name'])) ?><?php endif; ?></span>
					<span class="avatar-edit" aria-hidden="true"><?= icon('camera') ?></span>
					<input type="file" id="avatar-input" accept="image/jpeg,image/png,image/webp,image/gif" class="sr-only" aria-label="Upload a profile photo">
				</label>
				<div>
					<h2 data-bind="User_Name"><?= e($user['User_Name']) ?></h2>
					<p>Member since <?= e(date('M Y', strtotime($user['Created_at']))) ?></p>
				</div>
			</div>

			<div class="points" data-loyalty='<?= e(json_encode($loyalty)) ?>'>
				<div class="points-head">
					<small class="muted">Loyalty points</small>
					<span class="tier tier-<?= e($loyalty['tier']) ?>"><?= e($loyalty['tier']) ?></span>
				</div>
				<div class="points-value" data-count="<?= (int) $loyalty['points'] ?>">0</div>
				<div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $loyalty['progress'] ?>"><i></i></div>
				<small class="tiny">
					<?php if ($loyalty['next']): ?>
						<?= number_format($loyalty['to_next']) ?> pts to <?= e($loyalty['next']) ?>
					<?php else: ?>
						Top tier reached — thank you!
					<?php endif; ?>
				</small>
			</div>

			<div class="mini-stats">
				<div><small>Orders</small><strong data-count="<?= (int) $totals['orders'] ?>">0</strong></div>
				<div><small>Spent</small><strong data-count="<?= (float) $totals['spent'] ?>" data-prefix="RM" data-decimals="0">0</strong></div>
				<div><small>Rate</small><strong>1pt/RM</strong></div>
			</div>
		</section>

		<section class="glass card card-glow" data-reveal>
			<div class="card-title">
				<h3>Contact info</h3>
				<button class="btn btn-sm btn-glass" data-open-modal="contact-modal"><?= '<svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>' ?>Edit</button>
			</div>
			<div class="info-list">
				<div class="info-row"><span>Username</span><strong data-bind="User_Name"><?= e($user['User_Name']) ?></strong></div>
				<div class="info-row"><span>Email</span><strong data-bind="Email"><?= e($user['Email'] ?? '—') ?></strong></div>
				<div class="info-row"><span>Phone</span><strong data-bind="Contain_number"><?= e($user['Contain_number'] ?: '—') ?></strong></div>
				<div class="info-row"><span>Password</span><button class="btn btn-sm btn-ghost" data-open-modal="password-modal">Change</button></div>
			</div>
		</section>
	</div>

	<!-- ============ RIGHT: addresses, orders, danger zone ============ -->
	<div class="dash-col">
		<section class="glass card" data-reveal>
			<div class="card-title">
				<div><h3>Delivery addresses</h3><p class="tiny">Your default address is used at checkout.</p></div>
				<button class="btn btn-sm btn-primary" data-address-new>+ Add address</button>
			</div>
			<div class="address-grid" id="address-grid"></div>
			<script type="application/json" id="address-data"><?= json_encode($addresses, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
		</section>

		<section class="glass card" data-reveal>
			<div class="card-title">
				<h3>Recent orders</h3>
				<a class="btn btn-sm btn-ghost" href="<?= e(url('customer/orders.php')) ?>">View all</a>
			</div>
			<?php if (!$recent): ?>
				<div class="empty">No orders yet. Your first cup earns your first points.</div>
			<?php else: ?>
				<div class="orders">
					<?php foreach ($recent as $o): ?>
						<div class="order">
							<div><strong><?= e($o['cafe'] ?? 'Café') ?></strong><small>#<?= (int) $o['Payment_ID'] ?> · <?= e(date('d M Y, g:ia', strtotime($o['Created_at']))) ?></small></div>
							<div style="display:flex;align-items:center;gap:12px">
								<span class="pill <?= $statusPill[$o['Payment_status']] ?? 'pill-muted' ?>"><?= e(ucfirst(strtolower($o['Payment_status']))) ?></span>
								<strong class="mono">RM<?= number_format((float) $o['Payment_amount'], 2) ?></strong>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>

		<section class="glass card danger-zone" data-reveal>
			<div class="danger-row">
				<div>
					<h3>Deactivate account</h3>
					<p>Hides your profile and signs you out. Your order history is kept for our records. Contact support to restore it.</p>
				</div>
				<label class="switch" title="Deactivate account">
					<input type="checkbox" id="deactivate-toggle" aria-label="Deactivate account">
					<span></span>
				</label>
			</div>
		</section>
	</div>
</div>

<!-- ============ MODALS ============ -->
<div class="modal" id="address-modal">
	<div class="modal-backdrop"></div>
	<div class="modal-panel wide" role="dialog" aria-modal="true" aria-labelledby="address-title">
		<div class="modal-head">
			<div><h2 id="address-title">Add address</h2><p>Where should the courier go?</p></div>
			<button class="icon-btn" data-close-modal aria-label="Close"><svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
		</div>
		<form id="address-form" novalidate>
			<input type="hidden" name="action" value="create">
			<input type="hidden" name="Address_ID" value="">
			<input type="hidden" name="Latitude" value="">
			<input type="hidden" name="Longitude" value="">
			<div class="form-grid">
				<label class="field"><span>Label</span>
					<select class="select" name="Label"><option>Home</option><option>Work</option><option>Campus</option><option>Other</option></select>
					<span class="field-error"></span></label>
				<label class="field"><span>Recipient name</span><input class="input" name="Recipient" required autocomplete="name"><span class="field-error"></span></label>
				<label class="field"><span>Phone</span><input class="input" name="Phone" type="tel" required autocomplete="tel"><span class="field-error"></span></label>
				<label class="field"><span>Postcode</span><input class="input" name="Postcode" required inputmode="numeric" maxlength="10" autocomplete="postal-code"><span class="field-error"></span></label>
				<label class="field span-2"><span>Address line 1</span><input class="input" name="Line1" required autocomplete="address-line1"><span class="field-error"></span></label>
				<label class="field span-2"><span>Address line 2 <em class="tiny">(optional)</em></span><input class="input" name="Line2" autocomplete="address-line2"><span class="field-error"></span></label>
				<label class="field"><span>City</span><input class="input" name="City" required autocomplete="address-level2"><span class="field-error"></span></label>
				<label class="field"><span>State</span>
					<select class="select" name="State" required>
						<?php foreach (['Kuala Lumpur', 'Selangor', 'Putrajaya', 'Johor', 'Kedah', 'Kelantan', 'Labuan', 'Melaka', 'Negeri Sembilan', 'Pahang', 'Penang', 'Perak', 'Perlis', 'Sabah', 'Sarawak', 'Terengganu'] as $s): ?>
							<option><?= $s ?></option>
						<?php endforeach; ?>
					</select><span class="field-error"></span></label>
				<label class="check span-2"><input type="checkbox" name="Is_default" value="1"> Make this my default address</label>
				<div class="span-2" style="border-top:1px solid var(--border,rgba(255,255,255,.08));padding-top:12px;margin-top:4px;">
					<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
						<button type="button" class="btn btn-sm btn-glass" id="gps-btn">
							<svg viewBox="0 0 24 24" width="16" height="16" style="display:inline-block;vertical-align:middle;margin-right:4px;"><path d="M12 2a8 8 0 0 0-8 8c0 5.4 7.05 11.5 7.35 11.76a1 1 0 0 0 1.3 0C12.95 21.5 20 15.4 20 10a8 8 0 0 0-8-8Z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="10" r="2.5" fill="none" stroke="currentColor" stroke-width="2"/></svg>
							Use My Location
						</button>
						<span class="tiny" id="gps-status" style="opacity:.6;"></span>
					</div>
					<label class="check" style="font-size:12px;opacity:.7;">
						<input type="checkbox" name="GeocodeConsent" value="1">
						Allow automatic address-to-map conversion via OpenStreetMap Nominatim. Your address text is sent to their free geocoding service to find your map location.
					</label>
				</div>
			</div>
			<div class="form-actions">
				<button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
				<button type="submit" class="btn btn-primary">Save address</button>
			</div>
		</form>
	</div>
</div>

<div class="modal" id="contact-modal">
	<div class="modal-backdrop"></div>
	<div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="contact-title">
		<div class="modal-head">
			<div><h2 id="contact-title">Contact info</h2><p>Used for order updates and receipts.</p></div>
			<button class="icon-btn" data-close-modal aria-label="Close"><svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
		</div>
		<form id="contact-form" novalidate>
			<input type="hidden" name="action" value="update_contact">
			<div class="form-grid">
				<label class="field span-2"><span>Username</span><input class="input" name="User_Name" value="<?= e($user['User_Name']) ?>" required><span class="field-error"></span></label>
				<label class="field span-2"><span>Email</span><input class="input" type="email" name="Email" value="<?= e($user['Email']) ?>" required><span class="field-error"></span></label>
				<label class="field span-2"><span>Phone</span><input class="input" type="tel" name="Contain_number" value="<?= e($user['Contain_number']) ?>"><span class="field-error"></span></label>
			</div>
			<div class="form-actions">
				<button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
				<button type="submit" class="btn btn-primary">Save changes</button>
			</div>
		</form>
	</div>
</div>

<div class="modal" id="password-modal">
	<div class="modal-backdrop"></div>
	<div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="pw-title">
		<div class="modal-head">
			<div><h2 id="pw-title">Change password</h2><p>Use at least 8 characters with a letter and a number.</p></div>
			<button class="icon-btn" data-close-modal aria-label="Close"><svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
		</div>
		<form id="password-form" novalidate>
			<input type="hidden" name="action" value="change_password">
			<div class="form-grid">
				<label class="field span-2"><span>Current password</span><input class="input" type="password" name="current" autocomplete="current-password" required><span class="field-error"></span></label>
				<label class="field span-2"><span>New password</span><input class="input" type="password" name="new" autocomplete="new-password" required data-strength="#pw-strength"><span class="field-error"></span><div id="pw-strength"></div></label>
				<label class="field span-2"><span>Confirm new password</span><input class="input" type="password" name="confirm" autocomplete="new-password" required><span class="field-error"></span></label>
			</div>
			<div class="form-actions">
				<button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
				<button type="submit" class="btn btn-primary">Update password</button>
			</div>
		</form>
	</div>
</div>

<?php layout_end(['pages/dashboard.js']);
