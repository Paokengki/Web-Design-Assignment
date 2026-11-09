<?php
/** Café browser — replaces sidebar/home.php. Public; search covers café names, types and dishes. */
require __DIR__ . '/../app/layout/master.php';
require __DIR__ . '/../app/services/images.php';

$user = current_user();
$cafes = q_all('SELECT r.Restaurant_ID, r.Name, r.Restaurant_type, r.Rating, r.Address,
	(SELECT COUNT(*) FROM Food f WHERE f.Restaurant_ID = r.Restaurant_ID) AS dishes,
	(SELECT MIN(amount) FROM Food f WHERE f.Restaurant_ID = r.Restaurant_ID) AS from_price
	FROM Restaurant r ORDER BY r.Rating DESC, r.Name');
foreach ($cafes as &$c) {
	$c['image'] = cafe_image_url($c['Name']);
	$c['url'] = url('customer/cafe.php?id=' . (int) $c['Restaurant_ID']);
	$c['matches'] = [];
}
unset($c);

layout_start(['title' => 'Cafés', 'nav' => 'shop', 'active' => 'cafes', 'styles' => ['components/cafe-card.css', 'pages/shop.css']]);
?>
<section class="shop-hero" data-reveal>
	<span class="eyebrow"><?= $user ? 'Welcome back, ' . e($user['User_Name']) : 'Order from nine cafés' ?></span>
	<h1>What are you <span class="grad-text">craving?</span></h1>
	<form class="shop-search glass" role="search" onsubmit="return false">
		<?= icon('search') ?>
		<label class="sr-only" for="cafe-q">Search cafés and dishes</label>
		<input id="cafe-q" class="input" type="search" placeholder="Try “latte”, “ramen” or a café name…" autocomplete="off">
		<kbd>/</kbd>
	</form>
	<div class="chips" role="radiogroup" aria-label="Café type">
		<?php foreach (['' => 'All', 'Cafe' => 'Cafés', 'Food' => 'Restaurants', 'Dessert' => 'Desserts'] as $val => $label): ?>
			<button type="button" class="chip <?= $val === '' ? 'is-active' : '' ?>" data-type="<?= e($val) ?>" role="radio" aria-checked="<?= $val === '' ? 'true' : 'false' ?>"><?= e($label) ?></button>
		<?php endforeach; ?>
		<span class="chips-spacer"></span>
		<label class="sr-only" for="cafe-sort">Sort</label>
		<select id="cafe-sort" class="select select-sm"><option value="rating">Top rated</option><option value="name">A–Z</option></select>
	</div>
</section>

<p class="result-line muted" id="cafe-result" aria-live="polite"><?= count($cafes) ?> cafés</p>
<div class="cafe-grid" id="cafe-grid"></div>
<script type="application/json" id="cafe-data"><?= json_encode($cafes, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

<?php layout_end(['pages/cafes.js']);
