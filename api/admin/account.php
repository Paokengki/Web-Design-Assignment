<?php
/**
 * Signed-in staff member's own account (AJAX). Any active staff role.
 * POST action = change_password
 */
require __DIR__ . '/../../app/bootstrap.php';

$action = api_start();
$me = require_staff(STAFF_ROLES);
if ($action !== 'change_password') json_out(false, null, 'Unknown action.', 400);

$row = q_one('SELECT Password FROM Staff WHERE Staff_ID = ?', [(int) $me['Staff_ID']]);
$save = fn(string $h) => q('UPDATE Staff SET Password = ? WHERE Staff_ID = ?', [$h, (int) $me['Staff_ID']]);
$current = (string) ($_POST['current'] ?? '');
$new = (string) ($_POST['new'] ?? '');

if (!check_password($current, $row['Password'], $save)) json_out(false, ['fields' => ['current' => 'Current password is wrong.']], 'Current password is wrong.', 422);
if ($p = password_problem($new)) json_out(false, ['fields' => ['new' => $p]], $p, 422);
if ($new !== (string) ($_POST['confirm'] ?? '')) json_out(false, ['fields' => ['confirm' => 'Passwords do not match.']], 'Passwords do not match.', 422);
$save(password_hash($new, PASSWORD_DEFAULT));
session_regenerate_id(true);
json_out(true);
