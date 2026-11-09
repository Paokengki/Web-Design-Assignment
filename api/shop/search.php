<?php
/**
 * Café + dish search (AJAX, public).
 * POST action = search, q, type (Cafe|Food|Dessert|''), sort (rating|name)
 * Returns cafés that match by name/type OR serve a dish matching q, with up to 3 matching dishes each.
 */
require __DIR__ . '/../../app/bootstrap.php';
require __DIR__ . '/../../app/services/images.php';

$action = api_start();
if ($action !== 'search') json_out(false, null, 'Unknown action.', 400);

$qtext = mb_substr(input('q'), 0, 60);
$type = input('type');
$sort = input('sort') === 'name' ? 'r.Name ASC' : 'r.Rating DESC, r.Name ASC';
$like = '%' . addcslashes($qtext, '%_\\') . '%';

$where = ['1 = 1'];
$params = [];
if ($qtext !== '') {
	$where[] = '(r.Name LIKE ? OR r.Restaurant_type LIKE ? OR EXISTS (SELECT 1 FROM Food f WHERE f.Restaurant_ID = r.Restaurant_ID AND (f.Name LIKE ? OR f.Food_type LIKE ?)))';
	array_push($params, $like, $like, $like, $like);
}
if (in_array($type, ['Cafe', 'Food', 'Dessert'], true)) {
	$where[] = 'r.Restaurant_type = ?';
	$params[] = $type;
}

$cafes = q_all('SELECT r.Restaurant_ID, r.Name, r.Restaurant_type, r.Rating, r.Address,
		(SELECT COUNT(*) FROM Food f WHERE f.Restaurant_ID = r.Restaurant_ID) AS dishes,
		(SELECT MIN(amount) FROM Food f WHERE f.Restaurant_ID = r.Restaurant_ID) AS from_price
	FROM Restaurant r WHERE ' . implode(' AND ', $where) . " ORDER BY $sort", $params);

foreach ($cafes as &$c) {
	$c['image'] = cafe_image_url($c['Name']);
	$c['url'] = url('customer/cafe.php?id=' . (int) $c['Restaurant_ID']);
	$c['matches'] = [];
	if ($qtext !== '') {
		$c['matches'] = q_all('SELECT Food_ID, Name, amount FROM Food WHERE Restaurant_ID = ? AND (Name LIKE ? OR Food_type LIKE ?) ORDER BY Name LIMIT 3',
			[(int) $c['Restaurant_ID'], $like, $like]);
	}
}
unset($c);

json_out(true, $cafes);
