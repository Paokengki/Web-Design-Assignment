<?php
/**
 * Private order chats (AJAX). See app/services/chat.php.
 *
 * Customer (as=customer):
 *   action = order    (payment_id, since_restaurant, since_driver, since_admin)
 *                     → the order's three conversations with messages newer than each "since" id
 *   action = send     (payment_id, party, body)
 *
 * Staff (as=staff):
 *   action = threads  → inbox: every conversation this staff member may see, newest first
 *   action = messages (thread_id, since)
 *   action = send     (thread_id, body)
 *
 * Polling every ~2 s makes it real-time without WebSockets (XAMPP has none).
 */
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../app/services/chat.php';

$action = api_start();
$as = input('as', 'customer');

/** How many messages the other side has sent that this side has not seen yet. */
function chat_unread(array $t, string $side): int
{
	$readId = (int) ($side === 'customer' ? $t['Customer_read_id'] : $t['Staff_read_id']);
	$op = $side === 'customer' ? "<> 'customer'" : "= 'customer'";
	return (int) q_one("SELECT COUNT(*) n FROM Chat_Message WHERE Thread_ID = ? AND Message_ID > ? AND Sender $op", [(int) $t['Thread_ID'], $readId])['n'];
}

function chat_mark_read(array $t, string $side, array $messages): void
{
	if (!$messages) return;
	$last = end($messages)['id'];
	$col = $side === 'customer' ? 'Customer_read_id' : 'Staff_read_id';
	q("UPDATE Chat_Thread SET $col = GREATEST(COALESCE($col, 0), ?) WHERE Thread_ID = ?", [$last, (int) $t['Thread_ID']]);
}

const CHAT_PARTY_LABELS = ['restaurant' => 'Restaurant', 'driver' => 'Driver', 'admin' => 'CafeDash support'];

/* ------------------------------------------------------------------ customer */
if ($as === 'customer') {
	$user = require_customer();
	$pid = (int) input('payment_id');
	$order = q_one('SELECT Payment_ID FROM Payment WHERE Payment_ID = ? AND User_ID = ?', [$pid, (int) $user['User_ID']]);
	if (!$order) json_out(false, null, 'Order not found.', 404);

	switch ($action) {
		case 'order':
			$cafe = chat_order_cafe($pid);
			$cafeName = $cafe ? q_one('SELECT Name FROM Restaurant WHERE Restaurant_ID = ?', [$cafe])['Name'] : 'Restaurant';
			$out = [];
			foreach (CHAT_PARTIES as $party) {
				$t = chat_thread($pid, $party);
				if (!$t) {
					$out[$party] = ['available' => false, 'title' => 'Driver', 'subtitle' => 'Available once a driver is assigned', 'messages' => [], 'unread' => 0];
					continue;
				}
				$msgs = chat_messages((int) $t['Thread_ID'], (int) input('since_' . $party, '0'));
				$subtitle = match ($party) {
					'restaurant' => $cafeName,
					'driver' => ($d = q_one('SELECT Name, Plate_number FROM Staff WHERE Staff_ID = ?', [(int) $t['Driver_ID']])) ? $d['Name'] . ($d['Plate_number'] ? ' · ' . $d['Plate_number'] : '') : 'Your driver',
					'admin' => 'We usually reply within a few minutes',
				};
				$out[$party] = [
					'available' => true,
					'title'     => $party === 'restaurant' ? $cafeName : CHAT_PARTY_LABELS[$party],
					'subtitle'  => $subtitle,
					'messages'  => $msgs,
					'unread'    => chat_unread($t, 'customer'),
				];
			}
			json_out(true, $out);

		case 'read':
			$t = chat_thread($pid, input('party'));
			if ($t) {
				$last = q_one('SELECT MAX(Message_ID) m FROM Chat_Message WHERE Thread_ID = ?', [(int) $t['Thread_ID']])['m'];
				if ($last) q('UPDATE Chat_Thread SET Customer_read_id = ? WHERE Thread_ID = ?', [(int) $last, (int) $t['Thread_ID']]);
			}
			json_out(true);

		case 'send':
			$t = chat_thread($pid, input('party'));
			if (!$t) json_out(false, null, 'This chat is not open yet.', 409);
			try {
				$id = chat_post((int) $t['Thread_ID'], 'customer', input('body'));
			} catch (InvalidArgumentException $e) {
				json_out(false, null, $e->getMessage(), 422);
			}
			q('UPDATE Chat_Thread SET Customer_read_id = ? WHERE Thread_ID = ?', [$id, (int) $t['Thread_ID']]);
			json_out(true, ['id' => $id]);
	}
	json_out(false, null, 'Unknown action.', 400);
}

/* ------------------------------------------------------------------ staff */
$staff = require_staff(STAFF_ROLES);

/** The thread, if this staff member may see it. */
function staff_thread(array $staff): array
{
	$t = q_one('SELECT * FROM Chat_Thread WHERE Thread_ID = ?', [(int) input('thread_id')]);
	if (!$t || !chat_staff_can($staff, $t)) json_out(false, null, 'Conversation not found.', 404);
	return $t;
}

switch ($action) {
	case 'threads':
		$where = '1 = 1';
		$args = [];
		if (!in_array($staff['Role'], ADMIN_ROLES, true)) {
			if ($staff['Role'] === 'Kitchen Staff') {
				$where = "t.Party = 'restaurant' AND t.Restaurant_ID = ?";
				$args[] = (int) $staff['Assigned_Restaurant_ID'];
			} elseif ($staff['Role'] === 'Courier') {
				$where = "t.Party = 'driver' AND t.Driver_ID = ?";
				$args[] = (int) $staff['Staff_ID'];
			} else {
				json_out(true, ['threads' => [], 'can_reply' => []]);
			}
		}
		$party = input('party');
		if (in_array($party, CHAT_PARTIES, true)) { $where .= ' AND t.Party = ?'; $args[] = $party; }

		$rows = q_all("SELECT t.*, u.User_Name AS Username, r.Name AS Cafe, s.Name AS Driver, ot.Order_status,
				(SELECT m.Body FROM Chat_Message m WHERE m.Thread_ID = t.Thread_ID ORDER BY m.Message_ID DESC LIMIT 1) AS Last_body,
				(SELECT m.Sender FROM Chat_Message m WHERE m.Thread_ID = t.Thread_ID ORDER BY m.Message_ID DESC LIMIT 1) AS Last_sender
			FROM Chat_Thread t
			JOIN User u ON u.User_ID = t.User_ID
			LEFT JOIN Restaurant r ON r.Restaurant_ID = t.Restaurant_ID
			LEFT JOIN Staff s ON s.Staff_ID = t.Driver_ID
			LEFT JOIN Order_Tracking ot ON ot.Payment_ID = t.Payment_ID
			WHERE $where AND EXISTS (SELECT 1 FROM Chat_Message m WHERE m.Thread_ID = t.Thread_ID)
			ORDER BY t.Last_message_at DESC LIMIT 100", $args);

		json_out(true, ['threads' => array_map(fn($t) => [
			'id'        => (int) $t['Thread_ID'],
			'payment_id' => (int) $t['Payment_ID'],
			'party'     => $t['Party'],
			'customer'  => $t['Username'],
			'with'      => $t['Party'] === 'restaurant' ? ($t['Cafe'] ?? 'Restaurant') : ($t['Party'] === 'driver' ? ($t['Driver'] ?? 'Driver') : 'Support'),
			'status'    => $t['Order_status'],
			'last'      => $t['Last_body'],
			'last_sender' => $t['Last_sender'],
			'at'        => $t['Last_message_at'] ? date('c', strtotime($t['Last_message_at'])) : null,
			'unread'    => chat_unread($t, 'staff'),
			'can_reply' => chat_staff_sender($staff, $t) !== null,
		], $rows)]);

	case 'messages':
		$t = staff_thread($staff);
		$msgs = chat_messages((int) $t['Thread_ID'], (int) input('since', '0'));
		if (chat_staff_sender($staff, $t)) chat_mark_read($t, 'staff', $msgs); // admins only peeking don't mark others' chats read
		json_out(true, ['messages' => $msgs, 'can_reply' => chat_staff_sender($staff, $t) !== null]);

	case 'send':
		$t = staff_thread($staff);
		$sender = chat_staff_sender($staff, $t);
		if (!$sender) json_out(false, null, 'Only the ' . ($t['Party'] === 'admin' ? 'support team' : $t['Party']) . ' can reply in this chat.', 403);
		try {
			$id = chat_post((int) $t['Thread_ID'], $sender, input('body'), (int) $staff['Staff_ID']);
		} catch (InvalidArgumentException $e) {
			json_out(false, null, $e->getMessage(), 422);
		}
		q('UPDATE Chat_Thread SET Staff_read_id = ? WHERE Thread_ID = ?', [$id, (int) $t['Thread_ID']]);
		json_out(true, ['id' => $id]);
}
json_out(false, null, 'Unknown action.', 400);
