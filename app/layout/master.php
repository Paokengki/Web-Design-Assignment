<?php
/**
 * Master layout — the v2 replacement for base.php. Every new page uses it.
 *
 *   require __DIR__ . '/../app/layout/master.php';
 *   layout_start(['title' => 'Dashboard', 'nav' => 'shop', 'active' => 'dashboard', 'styles' => ['pages/dashboard.css']]);
 *   ...page HTML...
 *   layout_end(['pages/dashboard.js']);
 *
 * nav:    'shop'  customer-facing pages (works signed in or out, includes the cart drawer)
 *         'admin' control panel with sidebar
 *         'none'  bare page (auth screens)
 * styles: extra files under assets/css/      scripts: extra files under assets/js/
 */
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/icons.php';

const CORE_CSS = [
	'core/tokens.css', 'core/base.css', 'core/layout.css',
	'components/surfaces.css', 'components/buttons.css', 'components/forms.css',
	'components/feedback.css', 'components/overlays.css', 'components/data.css',
];
const CORE_JS = ['core/api.js', 'core/ui.js', 'core/motion.js'];

function layout_start(array $o = []): void
{
	$title  = $o['title'] ?? APP_NAME;
	$nav    = $o['nav'] ?? 'shop';
	$active = $o['active'] ?? '';
	$body   = $o['body_class'] ?? '';
	$GLOBALS['__layout_nav'] = $nav;
	?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
	<meta name="app-base" content="<?= e(url('')) ?>">
	<title><?= e($title) ?> · <?= APP_NAME ?></title>
	<link rel="icon" href="<?= e(url('material/images/logo.png')) ?>">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&display=swap">
	<?php foreach (array_merge(CORE_CSS, $nav === 'shop' ? ['components/cart.css'] : [], $o['styles'] ?? []) as $css): ?>
	<link rel="stylesheet" href="<?= e(asset('assets/css/' . $css)) ?>">
	<?php endforeach; ?>
</head>
<body class="<?= e($body) ?> nav-<?= e($nav) ?>">
	<a class="skip-link" href="#main">Skip to content</a>
	<div class="page-curtain" aria-hidden="true"></div>
	<div class="ambient" aria-hidden="true"><span></span><span></span><span></span></div>
	<div class="toast-stack" id="toasts" role="status" aria-live="polite"></div>
	<?php
	if ($nav === 'admin') {
		render_admin_sidebar($active);
		echo '<div class="admin-main">';
	} elseif ($nav === 'shop') {
		render_top_nav($active);
	}
	echo '<main id="main" class="main">';
	foreach (take_flashes() as $f) {
		echo '<div class="flash flash-' . e($f['type']) . '" data-reveal>' . e($f['message']) . '</div>';
	}
}

function layout_end(array $scripts = []): void
{
	$nav = $GLOBALS['__layout_nav'] ?? 'shop';
	echo '</main>';
	if ($nav === 'admin') {
		echo '</div>';
		render_staff_account_modal();
	}
	if ($nav === 'shop') {
		render_footer();
		render_cart_drawer();
	}
	$js = array_merge(CORE_JS, $nav === 'shop' ? ['core/cart.js'] : [], $nav === 'admin' ? ['pages/admin-shell.js'] : [], $scripts);
	?>
	<script>window.CD_ICONS = <?= json_encode(ICON_PATHS, JSON_HEX_TAG) ?>;</script>
	<script src="<?= e(asset('assets/vendor/gsap.min.js')) ?>"></script>
	<script src="<?= e(asset('assets/vendor/ScrollTrigger.min.js')) ?>"></script>
	<?php foreach ($js as $file): ?>
	<script src="<?= e(asset('assets/js/' . $file)) ?>"></script>
	<?php endforeach; ?>
</body>
</html>
<?php
}

/* ------------------------------------------------------------------ pieces */

function logo_mark(): string
{
	// Brand badge (material/images/logo.png). Decorative: the "CafeDash" text beside it names the link.
	return '<img class="logo-mark" src="' . e(url('material/images/logo.png')) . '" alt="" width="36" height="36" decoding="async">';
}

function avatar_url(?string $stored): ?string
{
	if (!$stored) return null;
	return url(preg_replace('#^(\.\./)+#', '', $stored));
}

function avatar_html(array $user, string $class = ''): string
{
	$src = avatar_url($user['Profile_Image'] ?? null);
	$inner = $src ? '<img src="' . e($src) . '" alt="">' : e(initials($user['User_Name'] ?? $user['Name'] ?? '?'));
	return '<span class="avatar ' . e($class) . '">' . $inner . '</span>';
}

function render_top_nav(string $active): void
{
	$user = current_user();
	$links = $user
		? ['cafes' => ['Cafés', 'customer/home.php'], 'orders' => ['Orders', 'customer/orders.php'], 'dashboard' => ['Dashboard', 'customer/dashboard.php'], 'feedback' => ['Feedback', 'customer/feedback.php']]
		: ['cafes' => ['Cafés', 'customer/home.php'], 'rewards' => ['Rewards', 'index.php#rewards'], 'feedback' => ['Feedback', 'customer/feedback.php']];
	?>
	<header class="topnav" data-nav>
		<a class="brand" href="<?= e(url('index.php')) ?>"><?= logo_mark() ?><span><?= APP_NAME ?></span></a>
		<nav class="topnav-links" id="topnav-links" aria-label="Main">
			<?php foreach ($links as $key => [$label, $href]): ?>
				<a class="<?= $active === $key ? 'is-active' : '' ?>" href="<?= e(url($href)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
			<?php endforeach; ?>
		</nav>
		<div class="topnav-actions">
			<button class="icon-btn cart-btn" type="button" data-cart-open aria-label="Open cart">
				<?= icon('bag') ?><span class="cart-count" data-cart-count hidden>0</span>
			</button>
			<?php if ($user): ?>
				<a class="nav-avatar" href="<?= e(url('customer/dashboard.php')) ?>" title="Dashboard"><?= avatar_html($user, 'avatar-sm') ?></a>
				<form method="post" action="<?= e(url('auth/logout.php')) ?>" class="hide-sm">
					<?= csrf_field() ?>
					<button class="btn btn-glass btn-sm" type="submit">Sign out</button>
				</form>
			<?php else: ?>
				<a class="btn btn-ghost btn-sm hide-sm" href="<?= e(url('auth/login.php')) ?>">Sign in</a>
				<a class="btn btn-primary btn-sm" data-magnetic href="<?= e(url('auth/register.php')) ?>">Join</a>
			<?php endif; ?>
			<button class="icon-btn nav-toggle" type="button" data-nav-toggle aria-controls="topnav-links" aria-expanded="false" aria-label="Menu"><?= icon('menu') ?></button>
		</div>
	</header>
	<?php
}

/** Admin sidebar. Each link lists the roles that may see it. */
function admin_nav_items(): array
{
	return [
		'overview'    => ['Overview', 'admin/overview.php', 'chart', ADMIN_ROLES],
		'promos'      => ['Promotions', 'admin/promos.php', 'tag', ADMIN_ROLES],
		'restaurants' => ['Cafés & menu', 'admin/restaurants.php', 'store', ADMIN_ROLES],
		'members'     => ['Members', 'admin/members.php', 'users', ADMIN_ROLES],
		'feedback'    => ['Feedback', 'admin/feedback.php', 'inbox', ADMIN_ROLES],
		'tracking'    => ['Live Orders', 'tracking/manager/deliveries.php', 'truck', ADMIN_ROLES],
		'staff'       => ['Staff & roles', 'admin/staff.php', 'shield', ['Super Admin']],
	];
}

function render_admin_sidebar(string $active): void
{
	$staff = current_staff();
	$unread = 0;
	try {
		$unread = (int) (q_one('SELECT COUNT(*) c FROM contact_us WHERE Is_read = 0')['c'] ?? 0);
	} catch (Throwable $e) { /* column added by migration_v3 */ }
	?>
	<aside class="sidebar glass">
		<a class="brand" href="<?= e(url('admin/overview.php')) ?>"><?= logo_mark() ?><span><?= APP_NAME ?><small>Control</small></span></a>
		<nav class="side-nav" aria-label="Control panel">
			<?php foreach (admin_nav_items() as $key => [$label, $href, $icon, $roles]):
				if (!in_array($staff['Role'] ?? '', $roles, true)) continue; ?>
				<a class="<?= $active === $key ? 'is-active' : '' ?>" href="<?= e(url($href)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>>
					<?= icon($icon) ?><span><?= e($label) ?></span>
					<?php if ($key === 'feedback' && $unread): ?><b class="side-badge" data-unread><?= $unread ?></b><?php endif; ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php if ($staff): ?>
		<div class="side-user">
			<span class="avatar avatar-sm"><?= e(initials($staff['Name'])) ?></span>
			<div><strong><?= e($staff['Name']) ?></strong><small><?= e($staff['Role']) ?></small></div>
			<button class="icon-btn" type="button" data-open-modal="account-modal" title="My account" aria-label="My account"><?= icon('settings') ?></button>
			<form method="post" action="<?= e(url('auth/logout.php')) ?>">
				<?= csrf_field() ?><input type="hidden" name="who" value="staff">
				<button class="icon-btn" title="Sign out" aria-label="Sign out"><?= icon('logout') ?></button>
			</form>
		</div>
		<?php endif; ?>
	</aside>
	<?php
}

function render_staff_account_modal(): void
{
	$staff = current_staff();
	if (!$staff) return;
	?>
	<div class="modal" id="account-modal">
		<div class="modal-backdrop"></div>
		<div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="account-title">
			<div class="modal-head">
				<div><h2 id="account-title">My account</h2><p><?= e($staff['Email'] ?? '') ?> · <?= e($staff['Role']) ?></p></div>
				<button class="icon-btn" data-close-modal aria-label="Close"><?= icon('x') ?></button>
			</div>
			<form id="account-form" novalidate>
				<input type="hidden" name="action" value="change_password">
				<div class="form-grid">
					<label class="field span-2"><span>Current password</span><input class="input" type="password" name="current" autocomplete="current-password" required><span class="field-error"></span></label>
					<label class="field span-2"><span>New password</span><input class="input" type="password" name="new" autocomplete="new-password" required data-strength="#account-strength"><span class="field-error"></span><div id="account-strength"></div></label>
					<label class="field span-2"><span>Confirm new password</span><input class="input" type="password" name="confirm" autocomplete="new-password" required><span class="field-error"></span></label>
				</div>
				<div class="form-actions">
					<button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
					<button type="submit" class="btn btn-primary">Update password</button>
				</div>
			</form>
		</div>
	</div>
	<?php
}

function render_cart_drawer(): void
{
	?>
	<div class="drawer" id="cart-drawer" aria-hidden="true">
		<div class="drawer-backdrop" data-cart-close></div>
		<aside class="drawer-panel" role="dialog" aria-modal="true" aria-labelledby="cart-title">
			<header class="drawer-head">
				<h2 id="cart-title">Your cart</h2>
				<button class="icon-btn" type="button" data-cart-close aria-label="Close cart"><?= icon('x') ?></button>
			</header>
			<div class="drawer-body" id="cart-items"><div class="empty">Loading…</div></div>
			<footer class="drawer-foot" id="cart-foot" hidden>
				<dl class="totals">
					<div><dt>Subtotal</dt><dd data-cart-subtotal></dd></div>
					<div class="discount" data-cart-discount-row hidden><dt>Discount <span class="pill pill-amber" data-cart-promo></span></dt><dd data-cart-discount></dd></div>
					<div><dt>SST 6%</dt><dd data-cart-sst></dd></div>
					<div class="total"><dt>Total</dt><dd data-cart-total></dd></div>
				</dl>
				<a class="btn btn-primary btn-lg btn-block" href="<?= e(url('customer/checkout.php')) ?>">Checkout</a>
				<button class="btn btn-ghost btn-sm btn-block" type="button" data-cart-clear>Clear cart</button>
			</footer>
		</aside>
	</div>
	<?php
}

function render_footer(): void
{
	?>
	<footer class="footer">
		<div class="brand"><?= logo_mark() ?><span><?= APP_NAME ?></span></div>
		<p>Every craving, one tap. Kuala Lumpur · <?= date('Y') ?></p>
		<nav aria-label="Footer"><a href="<?= e(url('customer/home.php')) ?>">Cafés</a><a href="<?= e(url('customer/feedback.php')) ?>">Feedback</a><a href="<?= e(url('auth/staff_login.php')) ?>">Staff sign in</a><a href="<?= e(url('tracking/kitchen/login.php')) ?>">Kitchen</a><a href="<?= e(url('tracking/courier/login.php')) ?>">Courier</a></nav>
	</footer>
	<?php
}

function initials(string $name): string
{
	$parts = preg_split('/[\s_]+/', trim($name)) ?: [];
	$out = '';
	foreach (array_slice($parts, 0, 2) as $p) $out .= mb_strtoupper(mb_substr($p, 0, 1));
	return $out ?: '?';
}

function money(float|string|null $v): string
{
	return 'RM' . number_format((float) $v, 2);
}
