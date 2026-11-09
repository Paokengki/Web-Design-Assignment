<?php
/** Left half of the auth pages: the cup frame on its white studio background. */
$visual_heading = $visual_heading ?? 'Freshly poured.';
$visual_text = $visual_text ?? 'Order from nine cafés and earn a point for every ringgit.';
$visual_frame = $visual_frame ?? 200;
?>
<div class="auth-visual">
	<img class="auth-bg" src="<?= e(url(sprintf('material/frame/frame_%04d.webp', $visual_frame))) ?>" alt="">
	<a class="brand" href="<?= e(url('index.php')) ?>"><?= logo_mark() ?><span><?= APP_NAME ?><small><?= e($visual_tag ?? 'Members') ?></small></span></a>
	<div>
		<h2 data-reveal><?= e($visual_heading) ?></h2>
		<p data-reveal><?= e($visual_text) ?></p>
	</div>
</div>
