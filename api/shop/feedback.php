<?php
/**
 * Feedback form (AJAX, public). Saves to contact_us, which the admin Feedback inbox reads.
 * POST action = send, name, email, phone, message, website (honeypot — must stay empty)
 */
require __DIR__ . '/../../app/bootstrap.php';

$action = api_start();
if ($action !== 'send') json_out(false, null, 'Unknown action.', 400);

// Bots fill every field; people never see this one.
if (input('website') !== '') json_out(true, ['sent' => true]);

$last = $_SESSION['feedback_sent_at'] ?? 0;
if (time() - $last < 30) json_out(false, null, 'Please wait a moment before sending more feedback.', 429);

$d = ['name' => input('name'), 'email' => input('email'), 'phone' => input('phone'), 'message' => input('message')];
$e = [];
if ($d['name'] === '' || mb_strlen($d['name']) > 100) $e['name'] = 'Tell us your name.';
if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $e['email'] = 'Enter a valid email so we can reply.';
if (!preg_match('/^[0-9+\-\s]{9,20}$/', $d['phone'])) $e['phone'] = 'Enter a valid phone number.';
if (mb_strlen($d['message']) < 10) $e['message'] = 'A little more detail, please (10+ characters).';
if (mb_strlen($d['message']) > 2000) $e['message'] = 'Keep it under 2000 characters.';
if ($e) json_out(false, ['fields' => $e], 'Please check the highlighted fields.', 422);

$user = current_user();
q('INSERT INTO contact_us (name, phone, email, message, User_ID) VALUES (?, ?, ?, ?, ?)',
	[$d['name'], $d['phone'], $d['email'], $d['message'], $user ? (int) $user['User_ID'] : null]);
$_SESSION['feedback_sent_at'] = time();
json_out(true, ['sent' => true]);
