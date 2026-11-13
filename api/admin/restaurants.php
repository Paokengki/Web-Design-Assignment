<?php
/**
 * Cafés & menu (AJAX, multipart for images). Managers + Super Admins.
 * POST action = list | get | save_cafe | delete_cafe | save_food | delete_food
 * Images are stored at material/<Café>/shop.jpg and material/<Café>/<Dish>.jpg (same layout as before).
 */
require __DIR__ . '/../../app/bootstrap.php';
require __DIR__ . '/../../app/services/images.php';

$action = api_start();
require_staff(ADMIN_ROLES);

const CAFE_TYPES = ['Cafe', 'Food', 'Dessert'];
const FOOD_TYPES = ['Beverage', 'Breakfast', 'Bakery', 'Rice', 'Noodles', 'Pasta', 'Pizza', 'Pie', 'Salad', 'Soup', 'Seafood', 'Chicken', 'Beef', 'Western', 'Dessert', 'Snack', 'Others'];

function cafe_row(int $id): array
{
	$c = q_one('SELECT r.*, (SELECT COUNT(*) FROM Food f WHERE f.Restaurant_ID = r.Restaurant_ID) dishes,
		(SELECT COUNT(*) FROM Payment p WHERE p.Restaurant_ID = r.Restaurant_ID) orders FROM Restaurant r WHERE r.Restaurant_ID = ?', [$id]);
	if (!$c) json_out(false, null, 'Café not found.', 404);
	$c['image'] = cafe_image_url($c['Name']) . '?v=' . time();
	return $c;
}

function foods_of(array $cafe): array
{
	$rows = q_all('SELECT Food_ID, Name, Food_type, detail, amount FROM Food WHERE Restaurant_ID = ? ORDER BY Food_type, Name', [(int) $cafe['Restaurant_ID']]);
	foreach ($rows as &$f) $f['image'] = food_image_url($cafe['Name'], $f['Name']);
	return $rows;
}

function staged_image(): ?string
{
	// Validate + convert the upload BEFORE touching the database, so a bad photo never leaves a half-saved row.
	if (empty($_FILES['image']['name'])) return null;
	$tmp = tempnam(sys_get_temp_dir(), 'cd_img');
	if ($err = save_uploaded_jpeg($_FILES['image'], $tmp)) {
		@unlink($tmp);
		json_out(false, ['fields' => ['image' => $err]], $err, 422);
	}
	return $tmp;
}

function place_image(?string $tmp, string $dest): void
{
	if ($tmp) {
		copy($tmp, $dest);
		@unlink($tmp);
	}
}

switch ($action) {
	case 'list':
		$rows = q_all('SELECT r.Restaurant_ID, r.Name, r.Restaurant_type, r.Rating, r.Address,
			(SELECT COUNT(*) FROM Food f WHERE f.Restaurant_ID = r.Restaurant_ID) dishes,
			(SELECT COUNT(*) FROM Payment p WHERE p.Restaurant_ID = r.Restaurant_ID) orders
			FROM Restaurant r ORDER BY r.Name');
		foreach ($rows as &$r) $r['image'] = cafe_image_url($r['Name']);
		json_out(true, ['cafes' => $rows, 'cafe_types' => CAFE_TYPES, 'food_types' => FOOD_TYPES]);

	case 'get':
		$c = cafe_row((int) input('Restaurant_ID'));
		json_out(true, ['cafe' => $c, 'foods' => foods_of($c)]);

	case 'save_cafe':
		$id = (int) input('Restaurant_ID');
		$d = ['Name' => input('Name'), 'Restaurant_type' => input('Restaurant_type'), 'Email' => input('Email'), 'Contain_number' => input('Contain_number'), 'Address' => input('Address'), 'Rating' => input('Rating'), 'Latitude' => input('Latitude'), 'Longitude' => input('Longitude')];
		$e = [];
		if ($d['Name'] === '' || mb_strlen($d['Name']) > 120) $e['Name'] = 'Enter the café name.';
		if (!in_array($d['Restaurant_type'], CAFE_TYPES, true)) $e['Restaurant_type'] = 'Pick a type.';
		if (!filter_var($d['Email'], FILTER_VALIDATE_EMAIL)) $e['Email'] = 'Enter a valid email.';
		if (!preg_match('/^[0-9+\-\s]{9,20}$/', $d['Contain_number'])) $e['Contain_number'] = 'Enter a valid phone number.';
		if ($d['Address'] === '') $e['Address'] = 'Enter the address.';
		if ($d['Rating'] !== '' && (!is_numeric($d['Rating']) || $d['Rating'] < 0 || $d['Rating'] > 5)) $e['Rating'] = '0 to 5.';
		// Validate coordinates if provided
		$latVal = $d['Latitude'] !== '' ? $d['Latitude'] : null;
		$lngVal = $d['Longitude'] !== '' ? $d['Longitude'] : null;
		$lat = ($latVal !== null && is_numeric($latVal) && $latVal >= -90 && $latVal <= 90) ? round((float)$latVal, 7) : null;
		$lng = ($lngVal !== null && is_numeric($lngVal) && $lngVal >= -180 && $lngVal <= 180) ? round((float)$lngVal, 7) : null;
		if (($latVal !== null && $lat === null) || ($lngVal !== null && $lng === null)) $e['Latitude'] = 'Enter valid coordinates (lat: -90 to 90, lng: -180 to 180).';
		if (!isset($e['Name']) && q_one('SELECT 1 FROM Restaurant WHERE Name = ? AND Restaurant_ID <> ?', [$d['Name'], $id])) $e['Name'] = 'Another café has this name.';
		if (!$id && empty($_FILES['image']['name'])) $e['image'] = 'Add a photo of the café.';
		if ($e) json_out(false, ['fields' => $e], 'Please check the highlighted fields.', 422);
		$img = staged_image();
		$rating = $d['Rating'] === '' ? null : round((float) $d['Rating'], 2);

		if ($id) {
			$old = cafe_row($id);
			rename_cafe_dir($old['Name'], $d['Name']);
			q('UPDATE Restaurant SET Name = ?, Restaurant_type = ?, Email = ?, Contain_number = ?, Address = ?, Rating = ?, Latitude = ?, Longitude = ? WHERE Restaurant_ID = ?',
				[$d['Name'], $d['Restaurant_type'], $d['Email'], $d['Contain_number'], $d['Address'], $rating, $lat, $lng, $id]);
		} else {
			q('INSERT INTO Restaurant (Name, Restaurant_type, Email, Contain_number, Address, Rating, Latitude, Longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
				[$d['Name'], $d['Restaurant_type'], $d['Email'], $d['Contain_number'], $d['Address'], $rating, $lat, $lng]);
			$id = (int) db()->insert_id;
		}
		place_image($img, cafe_dir($d['Name']) . '/shop.jpg');
		json_out(true, cafe_row($id));

	case 'delete_cafe':
		$c = cafe_row((int) input('Restaurant_ID'));
		// The old page deleted payment history here. We refuse instead: orders are financial records.
		if ((int) $c['orders'] > 0) json_out(false, null, $c['Name'] . ' has ' . $c['orders'] . ' orders on record, so it can’t be deleted.', 409);
		db()->begin_transaction();
		q('DELETE FROM Food WHERE Restaurant_ID = ?', [(int) $c['Restaurant_ID']]);
		q('DELETE FROM Restaurant WHERE Restaurant_ID = ?', [(int) $c['Restaurant_ID']]);
		db()->commit();
		json_out(true, ['Restaurant_ID' => (int) $c['Restaurant_ID']]); // image folder is kept on disk

	case 'save_food':
		$c = cafe_row((int) input('Restaurant_ID'));
		$fid = (int) input('Food_ID');
		$d = ['Name' => input('Name'), 'Food_type' => input('Food_type'), 'detail' => input('detail'), 'amount' => input('amount')];
		$e = [];
		if ($d['Name'] === '' || mb_strlen($d['Name']) > 120) $e['Name'] = 'Enter the dish name.';
		if (!in_array($d['Food_type'], FOOD_TYPES, true)) $e['Food_type'] = 'Pick a category.';
		if (!is_numeric($d['amount']) || $d['amount'] <= 0 || $d['amount'] > 9999) $e['amount'] = 'Price between RM0.10 and RM9999.';
		if (mb_strlen($d['detail']) > 500) $e['detail'] = 'Keep it under 500 characters.';
		if (!isset($e['Name']) && q_one('SELECT 1 FROM Food WHERE Restaurant_ID = ? AND Name = ? AND Food_ID <> ?', [(int) $c['Restaurant_ID'], $d['Name'], $fid])) $e['Name'] = 'This café already has a dish with this name.';
		if (!$fid && empty($_FILES['image']['name'])) $e['image'] = 'Add a photo of the dish.';
		if ($e) json_out(false, ['fields' => $e], 'Please check the highlighted fields.', 422);
		$img = staged_image();

		$dir = cafe_dir($c['Name']);
		if ($fid) {
			$old = q_one('SELECT Name FROM Food WHERE Food_ID = ? AND Restaurant_ID = ?', [$fid, (int) $c['Restaurant_ID']]);
			if (!$old) json_out(false, null, 'Dish not found.', 404);
			$from = $dir . '/' . safe_name($old['Name']) . '.jpg';
			$to = $dir . '/' . safe_name($d['Name']) . '.jpg';
			if ($from !== $to && is_file($from) && !is_file($to)) rename($from, $to);
			q('UPDATE Food SET Name = ?, Food_type = ?, detail = ?, amount = ? WHERE Food_ID = ?', [$d['Name'], $d['Food_type'], $d['detail'], round((float) $d['amount'], 2), $fid]);
		} else {
			q('INSERT INTO Food (Restaurant_ID, Name, Food_type, detail, amount) VALUES (?, ?, ?, ?, ?)', [(int) $c['Restaurant_ID'], $d['Name'], $d['Food_type'], $d['detail'], round((float) $d['amount'], 2)]);
			$fid = (int) db()->insert_id;
		}
		place_image($img, $dir . '/' . safe_name($d['Name']) . '.jpg');
		json_out(true, ['cafe' => cafe_row((int) $c['Restaurant_ID']), 'foods' => foods_of($c)]);

	case 'delete_food':
		$c = cafe_row((int) input('Restaurant_ID'));
		// Past orders keep the dish name and price (Payment_Item.Food_ID becomes NULL).
		q('DELETE FROM Food WHERE Food_ID = ? AND Restaurant_ID = ?', [(int) input('Food_ID'), (int) $c['Restaurant_ID']]);
		json_out(true, ['cafe' => cafe_row((int) $c['Restaurant_ID']), 'foods' => foods_of($c)]);

	default:
		json_out(false, null, 'Unknown action.', 400);
}
