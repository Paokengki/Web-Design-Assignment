<?php
/** Control-panel overview — replaces admin/_admin_ranking.php. KPIs, revenue trend, café ranking, best sellers. */
require __DIR__ . '/../app/layout/master.php';
$staff = require_staff(ADMIN_ROLES);

layout_start(['title' => 'Overview', 'nav' => 'admin', 'active' => 'overview', 'styles' => ['pages/admin.css']]);
?>
<div class="page-head" data-reveal>
	<div>
		<span class="eyebrow">Control panel</span>
		<h1>Good <?= (int) date('G') < 12 ? 'morning' : ((int) date('G') < 18 ? 'afternoon' : 'evening') ?>, <?= e(explode(' ', $staff['Name'])[0]) ?>.</h1>
		<p>How CafeDash is doing — successful payments only.</p>
	</div>
	<div class="seg" role="radiogroup" aria-label="Date range" id="range">
		<button type="button" data-days="7" role="radio" aria-checked="false">7 days</button>
		<button type="button" data-days="14" class="is-active" role="radio" aria-checked="true">14 days</button>
		<button type="button" data-days="30" role="radio" aria-checked="false">30 days</button>
	</div>
</div>

<div class="stats" id="kpis">
	<div class="glass stat card-glow" data-reveal><small>Revenue</small><strong data-kpi="revenue">–</strong><span class="delta" data-delta="revenue"></span></div>
	<div class="glass stat card-glow" style="--c:var(--violet)" data-reveal><small>Orders</small><strong data-kpi="orders">–</strong><span class="delta" data-delta="orders"></span></div>
	<div class="glass stat card-glow" style="--c:var(--cyan)" data-reveal><small>Members</small><strong data-kpi="members">–</strong><span class="tiny" data-kpi-note="buyers"></span></div>
	<div class="glass stat card-glow" style="--c:var(--ok)" data-reveal><small>Live promo codes</small><strong data-kpi="active_promos">–</strong><a class="tiny" href="<?= e(url('admin/promos.php')) ?>">Manage →</a></div>
	<div class="glass stat card-glow" style="--c:var(--warn)" data-reveal><small>Unread feedback</small><strong data-kpi="unread">–</strong><a class="tiny" href="<?= e(url('admin/feedback.php')) ?>">Open inbox →</a></div>
</div>

<div class="overview-grid">
	<section class="glass card chart-card" data-reveal>
		<div class="card-title">
			<div><h3>Daily revenue</h3><p class="tiny" id="rev-sub">RM, last 14 days</p></div>
			<button class="btn btn-sm btn-ghost" type="button" data-toggle-table="rev">Table view</button>
		</div>
		<div class="chart" id="rev-chart" role="img" aria-label="Daily revenue column chart"></div>
		<div class="chart-table" id="rev-table" hidden></div>
	</section>

	<section class="glass card chart-card" data-reveal>
		<div class="card-title">
			<div><h3>Café ranking</h3><p class="tiny">Orders, all time</p></div>
			<button class="btn btn-sm btn-ghost" type="button" data-toggle-table="rank">Table view</button>
		</div>
		<div class="hbars" id="rank-chart"></div>
		<div class="chart-table" id="rank-table" hidden></div>
	</section>

	<section class="glass card" data-reveal>
		<div class="card-title"><h3>Best sellers</h3><span class="tiny">Units sold</span></div>
		<ol class="top-list" id="top-items"></ol>
	</section>
</div>
<div class="chart-tip" id="chart-tip" role="tooltip" hidden></div>
<?php layout_end(['pages/overview.js']);
