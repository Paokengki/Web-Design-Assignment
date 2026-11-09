<?php
/**
 * Customer delivery addresses (AJAX).
 * POST action = list | create | update | delete | set_default
 * Every query is scoped to the signed-in user, so users can only touch their own rows.
 */
require __DIR__ . '/../../app/bootstrap.php';

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
		db()->begin_transaction();
		q('INSERT INTO User_Address (User_ID, Label, Recipient, Phone, Line1, Line2, City, Postcode, State) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
			[$uid, $d['Label'], $d['Recipient'], $d['Phone'], $d['Line1'], $d['Line2'], $d['City'], $d['Postcode'], $d['State']]);
		$id = (int) db()->insert_id;
		if (input('Is_default') === '1' || $count === 0) set_default($uid, $id);
		db()->commit();
		json_out(true, list_addresses($uid));

	case 'update':
		$id = owned_id($uid);
		$d = address_input();
		db()->begin_transaction();
		q('UPDATE User_Address SET Label = ?, Recipient = ?, Phone = ?, Line1 = ?, Line2 = ?, City = ?, Postcode = ?, State = ? WHERE Address_ID = ? AND User_ID = ?',
			[$d['Label'], $d['Recipient'], $d['Phone'], $d['Line1'], $d['Line2'], $d['City'], $d['Postcode'], $d['State'], $id, $uid]);
		if (input('Is_default') === '1') set_default($uid, $id);
		db()->commit();
		json_out(true, list_addresses($uid));

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
