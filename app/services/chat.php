<?php
/**
 * Private order chats — strictly 1-on-1: one conversation per order per party.
 *
 *   restaurant : customer ↔ the café preparing the order (answered by that café's Kitchen Staff)
 *   driver     : customer ↔ the courier delivering it   (exists once a driver is assigned)
 *   admin      : customer ↔ CafeDash support             (answered by Managers / Super Admins)
 *
 * Tables: Chat_Thread (one row per order + party), Chat_Message (append-only).
 * "Real-time" = the pages poll for messages newer than the last id they have (every ~2 s).
 */

const CHAT_PARTIES = ['restaurant', 'driver', 'admin'];
const CHAT_MAX_LENGTH = 1000;

/** The café an order belongs to (multi-café orders: the first café, which also delivers). */
function chat_order_cafe(int $paymentId): ?int
{
	$p = q_one('SELECT Restaurant_ID FROM Payment WHERE Payment_ID = ?', [$paymentId]);
	if ($p && $p['Restaurant_ID'] !== null) return (int) $p['Restaurant_ID'];
	$first = q_one('SELECT f.Restaurant_ID FROM Payment_Item pi JOIN Food f ON f.Food_ID = pi.Food_ID
		WHERE pi.Payment_ID = ? AND f.Restaurant_ID IS NOT NULL ORDER BY pi.Payment_Item_ID LIMIT 1', [$paymentId]);
	return $first ? (int) $first['Restaurant_ID'] : null;
}

/**
 * Get (or create) an order's conversation with one party.
 * Returns null for the driver chat while no driver is assigned yet.
 */
function chat_thread(int $paymentId, string $party): ?array
{
	if (!in_array($party, CHAT_PARTIES, true)) return null;
	$t = q_one('SELECT * FROM Chat_Thread WHERE Payment_ID = ? AND Party = ?', [$paymentId, $party]);
	$order = q_one('SELECT p.User_ID, t.Courier_ID FROM Payment p LEFT JOIN Order_Tracking t ON t.Payment_ID = p.Payment_ID WHERE p.Payment_ID = ?', [$paymentId]);
	if (!$order) return null;

	if ($party === 'driver') {
		$driver = $order['Courier_ID'] !== null ? (int) $order['Courier_ID'] : null;
		if ($t) {
			// keep the thread pointing at the current driver
			if ($driver && (int) $t['Driver_ID'] !== $driver) {
				q('UPDATE Chat_Thread SET Driver_ID = ? WHERE Thread_ID = ?', [$driver, (int) $t['Thread_ID']]);
				$t['Driver_ID'] = $driver;
			}
			return $t;
		}
		if (!$driver) return null;
	}
	if ($t) return $t;

	q('INSERT IGNORE INTO Chat_Thread (Payment_ID, User_ID, Party, Restaurant_ID, Driver_ID) VALUES (?, ?, ?, ?, ?)', [
		$paymentId, (int) $order['User_ID'], $party,
		$party === 'restaurant' ? chat_order_cafe($paymentId) : null,
		$party === 'driver' ? (int) $order['Courier_ID'] : null,
	]);
	return q_one('SELECT * FROM Chat_Thread WHERE Payment_ID = ? AND Party = ?', [$paymentId, $party]);
}

/** Add a message. $sender = 'customer' | 'restaurant' | 'driver' | 'admin'. Returns the message id. */
function chat_post(int $threadId, string $sender, string $body, ?int $staffId = null, bool $auto = false): int
{
	$body = trim(preg_replace('/\s+/u', ' ', $body));
	if ($body === '') throw new InvalidArgumentException('Type a message first.');
	if (mb_strlen($body) > CHAT_MAX_LENGTH) throw new InvalidArgumentException('Messages can be up to ' . CHAT_MAX_LENGTH . ' characters.');
	q('INSERT INTO Chat_Message (Thread_ID, Sender, Staff_ID, Body, Is_auto) VALUES (?, ?, ?, ?, ?)', [$threadId, $sender, $staffId, $body, $auto ? 1 : 0]);
	$id = (int) db()->insert_id;
	q('UPDATE Chat_Thread SET Last_message_at = NOW() WHERE Thread_ID = ?', [$threadId]);
	return $id;
}

/** An automatic message from the café or driver of an order (used by the demo lifecycle). */
function chat_auto(int $paymentId, string $party, string $body): void
{
	$t = chat_thread($paymentId, $party);
	if (!$t) return;
	$staffId = $party === 'driver' && $t['Driver_ID'] !== null ? (int) $t['Driver_ID'] : null;
	chat_post((int) $t['Thread_ID'], $party, $body, $staffId, true);
}

/** Messages newer than $sinceId (oldest first), with the sender's display name. */
function chat_messages(int $threadId, int $sinceId = 0, int $limit = 200): array
{
	$rows = q_all('SELECT m.Message_ID, m.Sender, m.Body, m.Is_auto, m.Created_at, s.Name AS Staff_name
		FROM Chat_Message m LEFT JOIN Staff s ON s.Staff_ID = m.Staff_ID
		WHERE m.Thread_ID = ? AND m.Message_ID > ? ORDER BY m.Message_ID LIMIT ' . (int) $limit, [$threadId, $sinceId]);
	return array_map(fn($m) => [
		'id'     => (int) $m['Message_ID'],
		'sender' => $m['Sender'],
		'name'   => $m['Staff_name'],
		'body'   => $m['Body'],
		'auto'   => (bool) $m['Is_auto'],
		'at'     => date('c', strtotime($m['Created_at'])),
	], $rows);
}

/** Can this staff member read and answer this thread? Admin roles may see every conversation. */
function chat_staff_can(array $staff, array $thread): bool
{
	if (in_array($staff['Role'], ADMIN_ROLES, true)) return true;
	if ($thread['Party'] === 'restaurant' && $staff['Role'] === 'Kitchen Staff') {
		return (int) $staff['Assigned_Restaurant_ID'] > 0 && (int) $staff['Assigned_Restaurant_ID'] === (int) $thread['Restaurant_ID'];
	}
	if ($thread['Party'] === 'driver' && $staff['Role'] === 'Courier') {
		return (int) $staff['Staff_ID'] === (int) $thread['Driver_ID'];
	}
	return false;
}

/** Which party a staff member writes as in a thread (admins can only reply in admin chats). */
function chat_staff_sender(array $staff, array $thread): ?string
{
	if ($thread['Party'] === 'admin') return in_array($staff['Role'], ADMIN_ROLES, true) ? 'admin' : null;
	if ($thread['Party'] === 'restaurant') return $staff['Role'] === 'Kitchen Staff' && chat_staff_can($staff, $thread) ? 'restaurant' : null;
	if ($thread['Party'] === 'driver') return $staff['Role'] === 'Courier' && chat_staff_can($staff, $thread) ? 'driver' : null;
	return null;
}
