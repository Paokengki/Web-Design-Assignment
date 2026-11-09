<?php
/** Café menu — replaces sidebar/cafe.php. Pick a dish, set options, add to cart. */
require __DIR__ . '/../app/layout/master.php';
require __DIR__ . '/../app/services/cart.php';

$id = (int) ($_GET['id'] ?? 0);
$cafe = $id ? q_one('SELECT * FROM Restaurant WHERE Restaurant_ID = ?', [$id]) : null;
if (!$cafe) {
	flash('info', 'That café could not be found.');
	redirect('customer/home.php');
}

$groups = [];
foreach (q_all('SELECT Food_ID, Name, Food_type, detail, amount FROM Food WHERE Restaurant_ID = ? ORDER BY Food_type = "Beverage" DESC, Food_type, Name', [$id]) as $f) {
	$type = trim((string) $f['Food_type']) ?: 'Others';
	$groups[$type][] = [
		'id' => (int) $f['Food_ID'], 'name' => $f['Name'], 'type' => $type, 'detail' => (string) $f['detail'],
		'price' => (float) $f['amount'], 'drink' => is_drink_type($type), 'image' => food_image_url($cafe['Name'], $f['Name']),
	];
}
$slug = fn(string $s) => 'cat-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($s));

layout_start(['title' => $cafe['Name'], 'nav' => 'shop', 'active' => 'cafes', 'styles' => ['components/cafe-card.css', 'pages/shop.css']]);
?>
<a class="back-link" href="<?= e(url('customer/home.php')) ?>"><?= icon('arrow-left') ?>All cafés</a>

<header class="cafe-banner glass" data-reveal>
	<div class="cafe-banner-img"><img src="<?= e(cafe_image_url($cafe['Name'])) ?>" alt=""></div>
	<div class="cafe-banner-body">
		<span class="eyebrow"><?= e($cafe['Restaurant_type']) ?></span>
		<h1><?= e($cafe['Name']) ?></h1>
		<ul class="cafe-facts">
			<li><span class="rating"><?= icon('star') ?><?= number_format((float) $cafe['Rating'], 1) ?></span></li>
			<?php if ($cafe['Address']): ?><li><?= icon('pin') ?><?= e($cafe['Address']) ?></li><?php endif; ?>
			<?php if ($cafe['Contain_number']): ?><li><?= icon('phone') ?><a href="tel:<?= e(preg_replace('/\s+/', '', $cafe['Contain_number'])) ?>"><?= e($cafe['Contain_number']) ?></a></li><?php endif; ?>
		</ul>
	</div>
</header>

<?php if (!$groups): ?>
	<div class="empty glass">This café hasn’t published a menu yet.</div>
<?php else: ?>
	<nav class="cat-tabs glass" aria-label="Menu sections" data-cat-tabs>
		<?php foreach (array_keys($groups) as $i => $type): ?>
			<a href="#<?= e($slug($type)) ?>" class="<?= $i === 0 ? 'is-active' : '' ?>"><?= e($type) ?> <small><?= count($groups[$type]) ?></small></a>
		<?php endforeach; ?>
	</nav>

	<?php foreach ($groups as $type => $items): ?>
		<section class="menu-section" id="<?= e($slug($type)) ?>" data-cat>
			<h2><?= e($type) ?></h2>
			<div class="dish-grid">
				<?php foreach ($items as $it): ?>
					<button type="button" class="dish glass card-glow" data-reveal data-dish='<?= e(json_encode($it)) ?>'>
						<span class="dish-img"><img loading="lazy" src="<?= e($it['image']) ?>" alt="" onerror="this.parentNode.classList.add('no-img');this.remove()"></span>
						<span class="dish-body">
							<strong><?= e($it['name']) ?></strong>
							<?php if ($it['detail']): ?><span class="dish-detail"><?= e($it['detail']) ?></span><?php endif; ?>
							<span class="dish-foot"><b><?= money($it['price']) ?></b><span class="dish-add" aria-hidden="true"><?= icon('plus') ?></span></span>
						</span>
					</button>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endforeach; ?>
<?php endif; ?>

<!-- Item options -->
<div class="modal" id="dish-modal">
	<div class="modal-backdrop"></div>
	<div class="modal-panel dish-panel" role="dialog" aria-modal="true" aria-labelledby="dish-title">
		<div class="dish-hero"><img id="dish-img" alt=""></div>
		<button class="icon-btn dish-close" data-close-modal aria-label="Close"><?= icon('x') ?></button>
		<form id="dish-form" novalidate>
			<input type="hidden" name="food_id">
			<div class="modal-head">
				<div><h2 id="dish-title"></h2><p id="dish-detail"></p></div>
				<strong class="dish-price" id="dish-price"></strong>
			</div>
			<div id="drink-options" class="option-groups">
				<?php foreach (['sugar' => 'Sugar', 'ice' => 'Ice'] as $name => $label): ?>
					<fieldset class="option-group">
						<legend><?= $label ?></legend>
						<div class="seg seg-wide">
							<?php foreach (DRINK_OPTIONS as $opt): ?>
								<label><input type="radio" name="<?= $name ?>" value="<?= $opt ?>" <?= $opt === 'Normal' ? 'checked' : '' ?>><span><?= $opt ?></span></label>
							<?php endforeach; ?>
						</div>
					</fieldset>
				<?php endforeach; ?>
			</div>
			<label class="field"><span>Note for the kitchen <em class="tiny">(optional)</em></span>
				<input class="input" name="remark" maxlength="200" placeholder="No onions, extra hot…">
			</label>
			<div class="dish-actions">
				<div class="stepper stepper-lg" role="group" aria-label="Quantity">
					<button type="button" data-qty="-1" aria-label="One less"><?= icon('minus') ?></button>
					<output id="dish-qty">1</output>
					<button type="button" data-qty="1" aria-label="One more"><?= icon('plus') ?></button>
				</div>
				<button type="submit" class="btn btn-primary btn-lg" data-magnetic>Add · <span id="dish-total"></span></button>
			</div>
		</form>
	</div>
</div>

<?php layout_end(['pages/menu.js']);
