<?php
/**
 * Landing page: scroll-driven coffee-cup hero + café showcase.
 * (The old start page is still at login/loginHandle.php.)
 */
require __DIR__ . '/app/layout/master.php';

$cafes = q_all('SELECT Restaurant_ID, Name, Restaurant_type, Rating FROM Restaurant ORDER BY Rating DESC');
$user = current_user();

layout_start(['title' => 'Freshly poured', 'nav' => 'shop', 'body_class' => 'landing', 'styles' => ['components/cafe-card.css', 'pages/landing.css']]);
?>

<!-- ============ HERO: scroll-driven canvas ============ -->
<section class="hero" id="hero"
	data-frames-path="<?= e(url('material/frame/frame_')) ?>"
	data-frame-count="300"
	data-frame-ext=".webp">
	<div class="hero-sticky">
		<img class="hero-poster" src="<?= e(url('material/frame/frame_0001.webp')) ?>" alt="" aria-hidden="true">
		<canvas class="hero-canvas" aria-label="Coffee being poured into a white cup" role="img"></canvas>
		<div class="hero-dim" aria-hidden="true"></div>

		<div class="beat beat-left" data-beat="1">
			<span class="eyebrow">CafeDash · Kuala Lumpur</span>
			<h1>Freshly poured.<br><span class="grad-ink">Instantly ordered.</span></h1>
			<p>Nine independent cafés, one glass-smooth checkout. Scroll to pour.</p>
		</div>

		<div class="beat beat-right" data-beat="2">
			<span class="eyebrow">Made your way</span>
			<h2>Sugar level, ice level, a note to the barista.</h2>
			<p>Every drink is customised before it reaches the cup.</p>
		</div>

		<div class="beat beat-left" data-beat="3">
			<span class="eyebrow">Rewards</span>
			<h2>Every ringgit brews a point.</h2>
			<p>Climb from Bronze to Platinum and unlock member-only vouchers.</p>
		</div>

		<div class="beat beat-center" data-beat="4">
			<h2>Your next cup is <span class="grad-text">one tap away.</span></h2>
			<div class="beat-cta">
				<?php if ($user): ?>
					<a class="btn btn-primary btn-lg" data-magnetic href="<?= e(url('customer/home.php')) ?>">Order now</a>
					<a class="btn btn-glass btn-lg" href="<?= e(url('customer/dashboard.php')) ?>">My dashboard</a>
				<?php else: ?>
					<a class="btn btn-primary btn-lg" data-magnetic href="<?= e(url('auth/register.php')) ?>">Create account</a>
					<a class="btn btn-glass btn-lg" href="<?= e(url('auth/login.php')) ?>">Sign in</a>
				<?php endif; ?>
			</div>
		</div>

		<div class="hero-hud" aria-hidden="true">
			<span class="hero-loader"><i></i><b>Brewing 0%</b></span>
			<span class="scroll-hint">Scroll<i></i></span>
		</div>
		<div class="hero-progress" aria-hidden="true"><i></i></div>
	</div>
</section>

<!-- ============ CAFES ============ -->
<section class="section" id="cafes">
	<div class="section-head" data-reveal>
		<span class="eyebrow">The line-up</span>
		<h2>Every craving, <span class="grad-text">one tap.</span></h2>
	</div>
	<div class="cafe-grid">
		<?php foreach ($cafes as $i => $c): ?>
			<a class="cafe glass card-glow" data-reveal href="<?= e(url('customer/cafe.php?id=' . (int) $c['Restaurant_ID'])) ?>">
				<div class="cafe-img"><img loading="lazy" src="<?= e(url('material/' . rawurlencode($c['Name']) . '/shop.jpg')) ?>" alt=""></div>
				<div class="cafe-body">
					<div>
						<h3><?= e($c['Name']) ?></h3>
						<span class="tiny"><?= e($c['Restaurant_type']) ?></span>
					</div>
					<span class="rating"><?= CD_star() ?><?= number_format((float) $c['Rating'], 1) ?></span>
				</div>
			</a>
		<?php endforeach; ?>
	</div>
</section>

<!-- ============ REWARDS ============ -->
<section class="section" id="rewards">
	<div class="section-head" data-reveal>
		<span class="eyebrow">Loyalty</span>
		<h2>1 point for every RM1. <span class="muted">Automatically.</span></h2>
	</div>
	<div class="tiers">
		<?php foreach ([['Bronze', 0, 'Welcome voucher on sign-up'], ['Silver', 200, 'Birthday drink on us'], ['Gold', 500, 'Early access to seasonal menus'], ['Platinum', 1000, 'Priority courier + exclusive codes']] as [$tier, $pts, $perk]): ?>
			<div class="tier-card glass card-glow" data-reveal>
				<span class="tier tier-<?= $tier ?>"><?= $tier ?></span>
				<strong><?= number_format($pts) ?>+ <small>pts</small></strong>
				<p class="muted"><?= e($perk) ?></p>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<!-- ============ STEPS ============ -->
<section class="section steps-section">
	<div class="steps glass" data-reveal>
		<?php foreach ([['01', 'Browse', 'Pick a café and a craving.'], ['02', 'Customise', 'Sugar, ice and notes, per item.'], ['03', 'Pay', 'Secure card checkout with Stripe.'], ['04', 'Earn', 'Points land the moment you pay.']] as [$n, $t, $d]): ?>
			<div class="step"><span class="mono"><?= $n ?></span><h3><?= $t ?></h3><p class="muted"><?= $d ?></p></div>
		<?php endforeach; ?>
	</div>
</section>

<?php
function CD_star(): string
{
	return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9Z"/></svg>';
}
layout_end(['pages/hero.js']);
