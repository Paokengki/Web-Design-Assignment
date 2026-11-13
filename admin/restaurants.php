<?php
/** Cafés & menu — replaces admin/_admin_restaurants.php. Edit café details, photos and dishes without page reloads. */
require __DIR__ . '/../app/layout/master.php';
require_staff(ADMIN_ROLES);

layout_start(['title' => 'Cafés & menu', 'nav' => 'admin', 'active' => 'restaurants', 'styles' => ['pages/admin.css']]);
?>
<div class="page-head" data-reveal>
	<div>
		<span class="eyebrow">Control panel</span>
		<h1>Cafés &amp; menu</h1>
		<p>Pick a café to edit its details, photo and dishes.</p>
	</div>
	<div class="page-head-actions"><button class="btn btn-primary" data-magnetic data-cafe-new><?= icon('plus') ?>Add café</button></div>
</div>

<div class="rest-grid">
	<aside class="glass card rest-list" data-reveal>
		<label class="search search-sm"><?= icon('search') ?><span class="sr-only">Filter cafés</span><input class="input" type="search" id="cafe-filter" placeholder="Filter cafés…" autocomplete="off"></label>
		<div id="cafe-list" class="rest-items" role="listbox" aria-label="Cafés"></div>
	</aside>

	<section class="rest-detail" id="rest-detail">
		<div class="glass card empty-detail" data-reveal><?= icon('store') ?><p>Select a café on the left, or add a new one.</p></div>
	</section>
</div>

<!-- Café form -->
<div class="modal" id="cafe-modal">
	<div class="modal-backdrop"></div>
	<div class="modal-panel wide" role="dialog" aria-modal="true" aria-labelledby="cafe-title">
		<div class="modal-head">
			<div><h2 id="cafe-title">Add café</h2><p>Customers see this on the café page.</p></div>
			<button class="icon-btn" data-close-modal aria-label="Close"><?= icon('x') ?></button>
		</div>
		<form id="cafe-form" novalidate>
			<input type="hidden" name="action" value="save_cafe">
			<input type="hidden" name="Restaurant_ID" value="">
			<div class="form-grid">
				<label class="field span-2 image-field"><span>Photo</span>
					<span class="image-drop"><img alt="" hidden><span class="image-hint"><?= icon('image') ?>Choose a JPG, PNG or WEBP (max 3 MB)</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"></span>
					<span class="field-error"></span></label>
				<label class="field"><span>Name</span><input class="input" name="Name" required maxlength="120"><span class="field-error"></span></label>
				<label class="field"><span>Type</span><select class="select" name="Restaurant_type"><option>Cafe</option><option>Food</option><option>Dessert</option></select><span class="field-error"></span></label>
				<label class="field"><span>Email</span><input class="input" type="email" name="Email" required><span class="field-error"></span></label>
				<label class="field"><span>Phone</span><input class="input" type="tel" name="Contain_number" required><span class="field-error"></span></label>
				<label class="field span-2"><span>Address</span><textarea class="textarea" name="Address" rows="2" required></textarea><span class="field-error"></span></label>
				<label class="field"><span>Rating <em class="tiny">(0–5)</em></span><input class="input" type="number" name="Rating" min="0" max="5" step="0.1"><span class="field-error"></span></label>
				<label class="field"><span>Latitude <em class="tiny">(map)</em></span><input class="input" type="text" name="Latitude" placeholder="e.g. 3.0733" step="0.0000001"><span class="field-error"></span></label>
				<label class="field"><span>Longitude <em class="tiny">(map)</em></span><input class="input" type="text" name="Longitude" placeholder="e.g. 101.5830" step="0.0000001"><span class="field-error"></span></label>
				<p class="tiny span-2" style="margin-bottom:.5rem;color:var(--muted)">Map coordinates are needed for the delivery tracking map. Find them via Google Maps (right-click → coordinates) or OpenStreetMap.</p>
			</div>
			<div class="form-actions">
				<button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
				<button type="submit" class="btn btn-primary">Save café</button>
			</div>
		</form>
	</div>
</div>

<!-- Dish form -->
<div class="modal" id="food-modal">
	<div class="modal-backdrop"></div>
	<div class="modal-panel wide" role="dialog" aria-modal="true" aria-labelledby="food-title">
		<div class="modal-head">
			<div><h2 id="food-title">Add dish</h2><p id="food-sub"></p></div>
			<button class="icon-btn" data-close-modal aria-label="Close"><?= icon('x') ?></button>
		</div>
		<form id="food-form" novalidate>
			<input type="hidden" name="action" value="save_food">
			<input type="hidden" name="Restaurant_ID" value="">
			<input type="hidden" name="Food_ID" value="">
			<div class="form-grid">
				<label class="field span-2 image-field"><span>Photo</span>
					<span class="image-drop"><img alt="" hidden><span class="image-hint"><?= icon('image') ?>Choose a JPG, PNG or WEBP (max 3 MB)</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"></span>
					<span class="field-error"></span></label>
				<label class="field"><span>Dish name</span><input class="input" name="Name" required maxlength="120"><span class="field-error"></span></label>
				<label class="field"><span>Category</span><select class="select" name="Food_type" id="food-types"></select><span class="field-error"></span></label>
				<label class="field"><span>Price (RM)</span><input class="input" type="number" name="amount" min="0.1" step="0.1" required><span class="field-error"></span></label>
				<div class="field"><span class="field-label">Drink options</span><p class="tiny" id="drink-hint">Beverages get sugar &amp; ice choices automatically.</p></div>
				<label class="field span-2"><span>Description</span><textarea class="textarea" name="detail" rows="2" maxlength="500"></textarea><span class="field-error"></span></label>
			</div>
			<div class="form-actions">
				<button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
				<button type="submit" class="btn btn-primary">Save dish</button>
			</div>
		</form>
	</div>
</div>
<?php layout_end(['pages/restaurants.js']);
