<?php
/**
 * Emergency staff password reset — for when email is down or nobody can sign in.
 * Command line ONLY (refuses to run in a browser). Prints a one-time password to hand over in person.
 *
 *   C:\xampp\php\php.exe tools\reset_staff_password.php --list
 *   C:\xampp\php\php.exe tools\reset_staff_password.php admin@cafedash.com
 *   C:\xampp\php\php.exe tools\reset_staff_password.php admin@cafedash.com "MyChosen#Pass9"
 *
 * Passwords are stored as bcrypt hashes; the plain text is only ever shown here, once.
 * Resetting also re-activates a Suspended account (not a Deactivated one).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$_SERVER['HTTP_HOST'] = 'localhost'; // bootstrap expects a web request
require __DIR__ . '/../app/bootstrap.php';

$arg = $argv[1] ?? '';

if ($arg === '' || $arg === '--help') {
	fwrite(STDOUT, "Usage:\n  php tools/reset_staff_password.php --list\n  php tools/reset_staff_password.php <email> [new-password]\n");
	exit(1);
}

if ($arg === '--list') {
	foreach (q_all('SELECT Staff_ID, Name, Email, Role, Status FROM Staff ORDER BY FIELD(Role, "Super Admin", "Manager", "Kitchen Staff", "Courier"), Name') as $s) {
		printf("%-4d %-24s %-34s %-14s %s\n", $s['Staff_ID'], $s['Name'], $s['Email'] ?? '(no email)', $s['Role'], $s['Status']);
	}
	exit(0);
}

$staff = q_one('SELECT Staff_ID, Name, Role, Status FROM Staff WHERE Email = ?', [strtolower($arg)]);
if (!$staff) {
	fwrite(STDERR, "No staff member with email $arg. Use --list to see emails.\n");
	exit(1);
}

$password = $argv[2] ?? random_password(14);
if ($problem = password_problem($password)) {
	fwrite(STDERR, "$problem\n");
	exit(1);
}

$newStatus = $staff['Status'] === 'Suspended' ? 'Active' : $staff['Status'];
q('UPDATE Staff SET Password = ?, Status = ? WHERE Staff_ID = ?', [password_hash($password, PASSWORD_DEFAULT), $newStatus, (int) $staff['Staff_ID']]);

fwrite(STDOUT, "\nPassword reset for {$staff['Name']} ({$staff['Role']}, {$newStatus}).\n");
fwrite(STDOUT, "New password: $password\n\n");
fwrite(STDOUT, "Hand it over in person, then ask them to change it under 'My account' in the control panel.\n");
if ($staff['Status'] === 'Deactivated') {
	fwrite(STDOUT, "Note: this account is Deactivated, so it still cannot sign in until a Super Admin reactivates it.\n");
}
