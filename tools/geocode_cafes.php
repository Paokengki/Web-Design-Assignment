<?php
/**
 * Give every café without map coordinates a location, using its address and
 * OpenStreetMap (app/services/geocode.php, ~1 request per second). Needs internet.
 * Command line only. Safe to re-run: cafés that already have coordinates are skipped.
 *
 *   C:\xampp\php\php.exe tools\geocode_cafes.php
 */
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
$_SERVER['HTTP_HOST'] = 'localhost';
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/services/geocode.php';

$rows = q_all('SELECT Restaurant_ID, Name, Address FROM Restaurant WHERE Latitude IS NULL OR Longitude IS NULL ORDER BY Restaurant_ID');
if (!$rows) {
	echo "Every café already has coordinates.\n";
	exit(0);
}
foreach ($rows as $r) {
	$hit = geocode_address((string) $r['Address'], '', '', '');
	if (!$hit) {
		// Retry with just the area (postcode + town), which OpenStreetMap finds more reliably.
		if (preg_match('/(\d{5})\s+([^,]+)/', (string) $r['Address'], $m)) $hit = geocode_address('', trim($m[2]), $m[1], '');
	}
	if ($hit) {
		q('UPDATE Restaurant SET Latitude = ?, Longitude = ? WHERE Restaurant_ID = ?', [round($hit['lat'], 7), round($hit['lng'], 7), (int) $r['Restaurant_ID']]);
		printf("%-34s %.5f, %.5f\n", $r['Name'], $hit['lat'], $hit['lng']);
	} else {
		printf("%-34s NOT FOUND — set it by hand in Cafés & menu\n", $r['Name']);
	}
}
