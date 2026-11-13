<?php
/**
 * Customer delivery addresses (AJAX).
 * POST action = list | create | update | delete | set_default
 * Every query is scoped to the signed-in user, so users can only touch their own rows.
 *
 * Coordinate handling (added):
 *   - If the customer provides GPS coordinates (from "Use My Location" button),
 *     those are saved directly to User_Address.Latitude / Longitude.
 *   - If the customer provides a text address and consents to geocoding,
 *     the address is converted to coordinates using the Nominatim geocoding service.
 *   - If geocoding fails, the address text is still saved — coordinates remain NULL.
 *   - Existing valid coordinates are never overwritten with NULL.
 */
require __DIR__ . '/../../app/bootstrap.php';
require __DIR__ . '/../../app/services/geocode.php';

$action = api_start();
$user = require_customer();
$uid = (int) $user['User_ID'];
const MAX_ADDRESSES = 10;

function list_addresses(int $uid): array
{
	return q_all('SELECT * FROM User_Address WHERE User_ID = ? ORDER BY Is_default DESC, Created_at DESC', [$uid]);
}

function address_input(): array
{
	$d = [
		'Label'     => input('Label', 'Home'),
		'Recipient' => input('Recipient'),
		'Phone'     => input('Phone'),
		'Line1'     => input('Line1'),
		'Line2'     => input('Line2'),
		'City'      => input('City'),
		'Postcode'  => input('Postcode'),
		'State'     => input('State'),
	];
	$errors = [];
	if (!in_array($d['Label'], ['Home', 'Work', 'Campus', 'Other'], true)) $errors['Label'] = 'Pick a label.';
	if ($d['Recipient'] === '' || mb_strlen($d['Recipient']) > 255) $errors['Recipient'] = 'Who is receiving the order?';
	if (!preg_match('/^[0-9+\-\s]{9,20}$/', $d['Phone'])) $errors['Phone'] = 'Enter a valid phone number.';
	if ($d['Line1'] === '' || mb_strlen($d['Line1']) > 255) $errors['Line1'] = 'Enter the street address.';
	if (mb_strlen($d['Line2']) > 255) $errors['Line2'] = 'Too long.';
	if ($d['City'] === '' || mb_strlen($d['City']) > 100) $errors['City'] = 'Enter a city.';
	if (!preg_match('/^\d{5}$/', $d['Postcode'])) $errors['Postcode'] = 'Malaysian postcodes have 5 digits.';
	if ($d['State'] === '' || mb_strlen($d['State']) > 100) $errors['State'] = 'Pick a state.';
	if ($errors) json_out(false, ['fields' => $errors], 'Please check the highlighted fields.', 422);
	$d['Line2'] = $d['Line2'] === '' ? null : $d['Line2'];
	return $d;
}

/**
 * Resolve coordinates for an address.
 *
 * Priority:
 *   1. GPS coordinates provided by the customer (from "Use My Location" button).
 *   2. Geocoding via Nominatim (only if customer consented).
 *   3. Existing coordinates on the current row (for updates — don't overwrite with NULL).
 *
 * @param array  $d           Address data from address_input()
 * @param int    $addressId   Address_ID being updated (0 for new addresses)
 * @return array              ['lat' => float|null, 'lng' => float|null, 'geocoded' => bool, 'source' => string]
 */
function resolve_coordinates(array $d, int $addressId = 0): array
{
	// 1. Check for GPS coordinates from the "Use My Location" button.
	$gpsLat = input('Latitude', '');
	$gpsLng = input('Longitude', '');

	if ($gpsLat !== '' && $gpsLng !== '') {
		$lat = (float) $gpsLat;
		$lng = (float) $gpsLng;
		// Validate reasonable ranges for Malaysia
		if ($lat >= 0.5 && $lat <= 8.0 && $lng >= 99.0 && $lng <= 120.0) {
			return ['lat' => $lat, 'lng' => $lng, 'geocoded' => false, 'source' => 'gps'];
		}
	}

	// 2. Check if the customer consented to geocoding.
	$consent = input('GeocodeConsent', '0');
	if ($consent === '1') {
		$result = geocode_address($d['Line1'], $d['City'], $d['Postcode'], $d['State']);
		if ($result !== null) {
			return ['lat' => $result['lat'], 'lng' => $result['lng'], 'geocoded' => true, 'source' => 'geocode'];
		}
		// Geocoding failed — fall through to preserve existing coordinates.
	}

	// 3. For updates: preserve existing coordinates (don't overwrite with NULL).
	if ($addressId > 0) {
		$existing = q_one('SELECT Latitude, Longitude FROM User_Address WHERE Address_ID = ?', [$addressId]);
		if ($existing && $existing['Latitude'] !== null && $existing['Longitude'] !== null) {
			return ['lat' => (float) $existing['Latitude'], 'lng' => (float) $existing['Longitude'], 'geocoded' => false, 'source' => 'preserved'];
		}
	}

	// No coordinates available.
	return ['lat' => null, 'lng' => null, 'geocoded' => false, 'source' => 'none'];
}

/** Makes one address the default and clears the flag on the others, in one transaction. */
function set_default(int $uid, int $id): void
{
	q('UPDATE User_Address SET Is_default = (Address_ID = ?) WHERE User_ID = ?', [$id, $uid]);
}

function owned_id(int $uid): int
{
	$id = (int) input('Address_ID');
	if (!$id || !q_one('SELECT 1 FROM User_Address WHERE Address_ID = ? AND User_ID = ?', [$id, $uid])) {
		json_out(false, null, 'Address not found.', 404);
	}
	return $id;
}

switch ($action) {
	case 'list':
		json_out(true, list_addresses($uid));

	case 'create':
		$d = address_input();
		$count = (int) q_one('SELECT COUNT(*) c FROM User_Address WHERE User_ID = ?', [$uid])['c'];
		if ($count >= MAX_ADDRESSES) json_out(false, null, 'You can save up to ' . MAX_ADDRESSES . ' addresses.', 422);
		$coords = resolve_coordinates($d, 0);
		db()->begin_transaction();
		q('INSERT INTO User_Address (User_ID, Label, Recipient, Phone, Line1, Line2, City, Postcode, State, Latitude, Longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			[$uid, $d['Label'], $d['Recipient'], $d['Phone'], $d['Line1'], $d['Line2'], $d['City'], $d['Postcode'], $d['State'], $coords['lat'], $coords['lng']]);
		$id = (int) db()->insert_id;
		if (input('Is_default') === '1' || $count === 0) set_default($uid, $id);
		db()->commit();
		$msg = 'Address added.';
		if ($coords['source'] === 'gps') $msg = 'Address added with GPS coordinates.';
		elseif ($coords['source'] === 'geocode') $msg = 'Address added. Map location found automatically.';
		elseif ($coords['source'] === 'none') $msg = 'Address added. Map location not available — you can use "Use My Location" to set it later.';
		json_out(true, list_addresses($uid), $msg);

	case 'update':
		$id = owned_id($uid);
		$d = address_input();
		$coords = resolve_coordinates($d, $id);
		db()->begin_transaction();
		q('UPDATE User_Address SET Label = ?, Recipient = ?, Phone = ?, Line1 = ?, Line2 = ?, City = ?, Postcode = ?, State = ?, Latitude = ?, Longitude = ? WHERE Address_ID = ? AND User_ID = ?',
			[$d['Label'], $d['Recipient'], $d['Phone'], $d['Line1'], $d['Line2'], $d['City'], $d['Postcode'], $d['State'], $coords['lat'], $coords['lng'], $id, $uid]);
		if (input('Is_default') === '1') set_default($uid, $id);
		db()->commit();
		$msg = 'Address updated.';
		if ($coords['source'] === 'gps') $msg = 'Address updated with GPS coordinates.';
		elseif ($coords['source'] === 'geocode') $msg = 'Address updated. Map location found automatically.';
		elseif ($coords['source'] === 'preserved') $msg = 'Address updated. Existing map location kept.';
		elseif ($coords['source'] === 'none') $msg = 'Address updated. Map location not available — you can use "Use My Location" to set it.';
		json_out(true, list_addresses($uid), $msg);

	case 'set_default':
		set_default($uid, owned_id($uid));
		json_out(true, list_addresses($uid));

	case 'delete':
		$id = owned_id($uid);
		db()->begin_transaction();
		$wasDefault = (int) q_one('SELECT Is_default FROM User_Address WHERE Address_ID = ?', [$id])['Is_default'];
		q('DELETE FROM User_Address WHERE Address_ID = ? AND User_ID = ?', [$id, $uid]);
		if ($wasDefault) {
			// promote the newest remaining address so there is always a default
			$next = q_one('SELECT Address_ID FROM User_Address WHERE User_ID = ? ORDER BY Created_at DESC LIMIT 1', [$uid]);
			if ($next) set_default($uid, (int) $next['Address_ID']);
		}
		db()->commit();
		json_out(true, list_addresses($uid));

	default:
		json_out(false, null, 'Unknown action.', 400);
}
