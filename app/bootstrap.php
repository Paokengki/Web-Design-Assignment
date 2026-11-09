<?php
/**
 * CafeDash v2 bootstrap — include this at the top of every new page and API endpoint.
 * Provides: session, DB connection, URL helper, escaping, CSRF, auth guards, JSON responses.
 */
declare(strict_types=1);

date_default_timezone_set('Asia/Kuala_Lumpur');

if (session_status() === PHP_SESSION_NONE) {
	session_set_cookie_params([
		'httponly' => true,
		'samesite' => 'Lax',
		'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
	]);
	session_start();
}

const APP_NAME = 'CafeDash';
const STAFF_ROLES = ['Super Admin', 'Manager', 'Kitchen Staff', 'Courier'];
const ASSIGNABLE_ROLES = ['Manager', 'Kitchen Staff', 'Courier'];
const ADMIN_ROLES = ['Super Admin', 'Manager'];

/* ------------------------------------------------------------------ DB */

function db(): mysqli
{
	static $conn = null;
	if ($conn === null) {
		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
		$conn = new mysqli('localhost', 'root', '', 'cafedash_db');
		$conn->set_charset('utf8mb4');
		$conn->query("SET time_zone = '+08:00'");
	}
	return $conn;
}

/** Run a prepared statement. Types are inferred (int → i, float → d, else s). */
function q(string $sql, array $params = []): mysqli_stmt
{
	$stmt = db()->prepare($sql);
	if ($params) {
		$types = '';
		foreach ($params as $p) {
			$types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
		}
		$stmt->bind_param($types, ...$params);
	}
	$stmt->execute();
	return $stmt;
}

function q_all(string $sql, array $params = []): array
{
	$res = q($sql, $params)->get_result();
	return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function q_one(string $sql, array $params = []): ?array
{
	$rows = q_all($sql, $params);
	return $rows[0] ?? null;
}

/* ------------------------------------------------------------------ URLs + output */

/** Absolute URL for a path inside the project, works at web root or in a sub-folder. */
function url(string $path = ''): string
{
	static $base = null;
	if ($base === null) {
		$docRoot = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
		$root = str_replace('\\', '/', (string) realpath(dirname(__DIR__)));
		$base = '';
		if ($docRoot !== '' && stripos($root, $docRoot) === 0) {
			$base = rtrim(substr($root, strlen($docRoot)), '/');
		}
	}
	return $base . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
	$file = dirname(__DIR__) . '/' . ltrim($path, '/');
	$v = is_file($file) ? filemtime($file) : 1;
	return url($path) . '?v=' . $v;
}

function e(?string $value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
	header('Location: ' . url($path));
	exit;
}

function flash(string $type, string $message): void
{
	$_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
	$f = $_SESSION['flash'] ?? [];
	unset($_SESSION['flash']);
	return $f;
}

/* ------------------------------------------------------------------ CSRF */

function csrf_token(): string
{
	if (empty($_SESSION['csrf'])) {
		$_SESSION['csrf'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['csrf'];
}

function csrf_field(): string
{
	return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
	$sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
	return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

/* ------------------------------------------------------------------ Passwords */

/** Returns an error message, or null if the password is strong enough. */
function password_problem(string $password): ?string
{
	if (strlen($password) < 8) return 'Password must be at least 8 characters.';
	if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
		return 'Password needs at least one letter and one number.';
	}
	return null;
}

/**
 * Check a password. Old accounts stored plain text; those still log in once,
 * and the password is immediately re-saved as a bcrypt hash.
 */
function check_password(string $plain, string $stored, callable $saveNewHash): bool
{
	$info = password_get_info($stored);
	if ($info['algo'] !== null && $info['algo'] !== 0) {
		if (!password_verify($plain, $stored)) return false;
		if (password_needs_rehash($stored, PASSWORD_DEFAULT)) $saveNewHash(password_hash($plain, PASSWORD_DEFAULT));
		return true;
	}
	if (str_starts_with($stored, '!disabled-') || !hash_equals($stored, $plain)) return false;
	$saveNewHash(password_hash($plain, PASSWORD_DEFAULT));
	return true;
}

function random_password(int $length = 12): string
{
	$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789@#%';
	do {
		$out = '';
		for ($i = 0; $i < $length; $i++) $out .= $chars[random_int(0, strlen($chars) - 1)];
	} while (password_problem($out) !== null);
	return $out;
}

/* ------------------------------------------------------------------ Auth */

/** Logged-in customer row, or null. Uses the same session key as the old pages. */
function current_user(): ?array
{
	static $user = false;
	if ($user === false) {
		$id = (int) ($_SESSION['user_id'] ?? 0);
		$user = $id ? q_one('SELECT * FROM User WHERE User_ID = ? AND Deleted_at IS NULL AND Suspend = 0', [$id]) : null;
	}
	return $user;
}

function current_staff(): ?array
{
	static $staff = false;
	if ($staff === false) {
		$id = (int) ($_SESSION['staff_id'] ?? 0);
		$staff = $id ? q_one("SELECT * FROM Staff WHERE Staff_ID = ? AND Status = 'Active'", [$id]) : null;
	}
	return $staff;
}

function is_ajax(): bool
{
	return str_starts_with($_SERVER['SCRIPT_NAME'] ?? '', url('api/'))
		|| ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

function require_customer(): array
{
	$user = current_user();
	if (!$user) {
		if (is_ajax()) json_out(false, null, 'Please sign in again.', 401);
		flash('info', 'Please sign in to continue.');
		redirect('auth/login.php');
	}
	return $user;
}

function require_staff(array $roles = ADMIN_ROLES): array
{
	$staff = current_staff();
	if (!$staff) {
		if (is_ajax()) json_out(false, null, 'Please sign in again.', 401);
		redirect('auth/staff_login.php');
	}
	if (!in_array($staff['Role'], $roles, true)) {
		if (is_ajax()) json_out(false, null, 'Your role cannot do this.', 403);
		http_response_code(403);
		exit('Forbidden: your role (' . e($staff['Role']) . ') cannot open this page.');
	}
	return $staff;
}

/* ------------------------------------------------------------------ JSON API */

function json_out(bool $ok, mixed $data = null, ?string $error = null, int $status = 200): never
{
	http_response_code($status);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['ok' => $ok, 'data' => $data, 'error' => $error], JSON_UNESCAPED_UNICODE);
	exit;
}

/**
 * Standard entry for every API endpoint: POST only, valid CSRF token, returns the action name.
 * Uncaught errors become a JSON 500 instead of an HTML page.
 */
function api_start(): string
{
	set_exception_handler(function (Throwable $ex) {
		error_log((string) $ex);
		if ($ex instanceof mysqli_sql_exception && $ex->getCode() === 1062) {
			json_out(false, null, 'That value is already in use.', 409);
		}
		json_out(false, null, 'Server error. Please try again.', 500);
	});
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(false, null, 'Use POST.', 405);
	if (!csrf_valid()) json_out(false, null, 'Your session expired. Refresh the page.', 419);
	return (string) ($_POST['action'] ?? '');
}

function input(string $key, string $default = ''): string
{
	$v = $_POST[$key] ?? $default;
	return is_string($v) ? trim($v) : $default;
}
