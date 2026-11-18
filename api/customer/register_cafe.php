<?php
/**
 * Café registration (AJAX, public). Saves to Restaurant_Application as Pending.
 * POST action = submit, Name, Restaurant_type, Owner_Name, Email, Contain_number, Address, website (honeypot)
 * Place at api/customer/register_cafe.php
 */
require __DIR__ . '/../../app/bootstrap.php';

$action = api_start();
if ($action !== 'submit') json_out(false, null, 'Unknown action.', 400);

// Bots fill every field; people never see this one.
if (input('website') !== '') json_out(true, ['sent' => true]);

if (time() - ($_SESSION['cafe_apply_at'] ?? 0) < 30) json_out(false, null, 'Please wait a moment before submitting again.', 429);

const APPLY_TYPES = ['Cafe', 'Food', 'Dessert'];

$d = [
	'Name'            => input('Name'),
	'Restaurant_type' => input('Restaurant_type'),
	'Owner_Name'      => input('Owner_Name'),
	'Email'           => strtolower(input('Email')),
	'Contain_number'  => input('Contain_number'),
	'Address'         => input('Address'),
];
$e = [];
if ($d['Name'] === '' || mb_strlen($d['Name']) > 120) $e['Name'] = 'Enter your café name.';
if (!in_array($d['Restaurant_type'], APPLY_TYPES, true)) $e['Restaurant_type'] = 'Pick a type.';
if ($d['Owner_Name'] === '' || mb_strlen($d['Owner_Name']) > 100) $e['Owner_Name'] = 'Enter the owner or manager’s name.';
if (!filter_var($d['Email'], FILTER_VALIDATE_EMAIL) || mb_strlen($d['Email']) > 150) $e['Email'] = 'Enter a valid email.';
if (!preg_match('/^[0-9+\-\s]{9,20}$/', $d['Contain_number'])) $e['Contain_number'] = 'Enter a valid phone number.';
if ($d['Address'] === '' || mb_strlen($d['Address']) > 255) $e['Address'] = 'Enter the café address (max 255 characters).';

if (!isset($e['Name']) && q_one('SELECT 1 FROM Restaurant WHERE Name = ?', [$d['Name']])) {
	$e['Name'] = 'A café with this name is already on CafeDash.';
}
if (!isset($e['Name']) && !isset($e['Email'])
	&& q_one("SELECT 1 FROM Restaurant_Application WHERE Status = 'Pending' AND (Email = ? OR Name = ?)", [$d['Email'], $d['Name']])) {
	$e['Email'] = 'You already have an application waiting for review.';
}
if (!isset($e['Email']) && q_one('SELECT 1 FROM Staff WHERE Email = ?', [$d['Email']])) {
	$e['Email'] = 'This email can’t be used for a new application. Try another one.';
}
if ($e) json_out(false, ['fields' => $e], 'Please check the highlighted fields.', 422);

q('INSERT INTO Restaurant_Application (Name, Restaurant_type, Owner_Name, Email, Contain_number, Address) VALUES (?, ?, ?, ?, ?, ?)',
	[$d['Name'], $d['Restaurant_type'], $d['Owner_Name'], $d['Email'], $d['Contain_number'], $d['Address']]);
$_SESSION['cafe_apply_at'] = time();
json_out(true, ['sent' => true]);
