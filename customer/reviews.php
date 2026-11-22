<?php
/** My reviews — rate delivered orders, edit within 24 hours, read admin replies. Place in customer/. */
require __DIR__ . '/../app/layout/master.php';
require_customer();

layout_start(['title' => 'My reviews', 'nav' => 'shop', 'active' => 'reviews', 'styles' => ['pages/reviews.css']]);
?>
<div class="page-head" data-reveal>
	<div>
		<span class="eyebrow">Feedback &amp; ratings</span>
		<h1>My reviews</h1>
		<p>Rate your delivered orders. You can edit a review for 24 hours after posting it.</p>
	</div>
</div>

<div class="seg rv-tabs" role="tablist" aria-label="Reviews" data-reveal>
	<button type="button" class="is-active" data-tab="to-review" role="tab" aria-selected="true">To review <span class="tiny" id="count-to-review"></span></button>
	<button type="button" data-tab="mine" role="tab" aria-selected="false">My reviews <span class="tiny" id="count-mine"></span></button>
</div>

<section id="panel-to-review" class="rv-grid" aria-live="polite"><div class="skeleton" style="height:140px"></div></section>
<section id="panel-mine" class="rv-grid" aria-live="polite" hidden></section>

<!-- Rate / edit a review -->
<div class="modal" id="review-modal">
	<div class="modal-backdrop"></div>
	<div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="review-title">
		<div class="modal-head">
			<div><h2 id="review-title">Rate your order</h2><p id="review-sub"></p></div>
			<button class="icon-btn" data-close-modal aria-label="Close"><?= icon('x') ?></button>
		</div>
		<form id="review-form" novalidate>
			<input type="hidden" name="action" value="create">
			<input type="hidden" name="Payment_ID" value="">
			<input type="hidden" name="Review_ID" value="">
			<fieldset class="field">
				<legend class="field-label">Your rating</legend>
				<div class="star-input">
					<?php for ($i = 5; $i >= 1; $i--): ?>
						<input type="radio" name="Rating" id="star<?= $i ?>" value="<?= $i ?>"><label for="star<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">★<span class="sr-only"><?= $i ?> stars</span></label>
					<?php endfor; ?>
				</div>
				<span class="field-error"></span>
			</fieldset>
			<label class="field"><span>Your review <em class="tiny" id="rv-count">0 / 1000</em></span>
				<textarea class="textarea" name="Comment" rows="5" maxlength="1000" placeholder="Tell us how the food, packaging and delivery were…" required></textarea>
				<span class="field-error"></span></label>
			<div class="form-actions">
				<button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
				<button type="submit" class="btn btn-primary">Submit review</button>
			</div>
		</form>
	</div>
</div>
<?php layout_end(['pages/my-reviews.js']);
