<?php
/**
 * Tracking system configuration — constants and helpers for order tracking.
 *
 * Order status flow:
 *   Pending → Accepted → Preparing → Ready for Pickup
 *           → Assigned → Out for Delivery → Delivered
 *           → Cancelled (terminal)
 *
 * Payment_status (existing, NOT modified) vs Order_status (new, separate):
 *   Payment.Payment_status tracks the Stripe payment: PENDING / SUCCEEDED / FAILED / REFUNDED
 *   Order_Tracking.Order_status tracks fulfillment: Pending → ... → Delivered
 *
 * Only orders with Payment_status = 'SUCCEEDED' get a tracking row.
 */

// Order status constants
const TRACK_STATUS_PENDING         = 'Pending';
const TRACK_STATUS_ACCEPTED        = 'Accepted';
const TRACK_STATUS_PREPARING       = 'Preparing';
const TRACK_STATUS_READY           = 'Ready for Pickup';
const TRACK_STATUS_ASSIGNED        = 'Assigned';
const TRACK_STATUS_OUT_FOR_DELIVERY = 'Out for Delivery';
const TRACK_STATUS_DELIVERED       = 'Delivered';
const TRACK_STATUS_CANCELLED       = 'Cancelled';

// All statuses in flow order (for timeline display)
const TRACK_STATUS_FLOW = [
	TRACK_STATUS_PENDING,
	TRACK_STATUS_ACCEPTED,
	TRACK_STATUS_PREPARING,
	TRACK_STATUS_READY,
	TRACK_STATUS_ASSIGNED,
	TRACK_STATUS_OUT_FOR_DELIVERY,
	TRACK_STATUS_DELIVERED,
];

// Statuses that are terminal (no further updates)
const TRACK_STATUS_TERMINAL = [TRACK_STATUS_DELIVERED, TRACK_STATUS_CANCELLED];

// AJAX polling interval (milliseconds)
const TRACK_POLL_INTERVAL_CUSTOMER = 5000;  // 5 seconds for customer map
const TRACK_POLL_INTERVAL_KITCHEN  = 4000;  // 4 seconds for kitchen queue
const TRACK_POLL_INTERVAL_COURIER  = 3000;  // 3 seconds for courier GPS
const TRACK_POLL_INTERVAL_MANAGER  = 5000;  // 5 seconds for manager dashboard

// Courier GPS update interval (milliseconds)
const TRACK_GPS_UPDATE_INTERVAL = 4000;     // 4 seconds

// Default estimated preparation time (minutes)
const TRACK_DEFAULT_PREP_MINUTES = 15;
const TRACK_DEFAULT_DELIVERY_MINUTES = 20;

// Map default center (Subang Jaya, Malaysia)
const TRACK_MAP_DEFAULT_LAT = 3.0733;
const TRACK_MAP_DEFAULT_LNG = 101.5830;
const TRACK_MAP_DEFAULT_ZOOM = 14;

/**
 * Human-readable label for each status.
 */
function track_status_label(string $status): string
{
	$labels = [
		TRACK_STATUS_PENDING          => 'Order Placed',
		TRACK_STATUS_ACCEPTED         => 'Order Accepted',
		TRACK_STATUS_PREPARING        => 'Preparing Your Food',
		TRACK_STATUS_READY            => 'Ready for Pickup',
		TRACK_STATUS_ASSIGNED         => 'Courier Assigned',
		TRACK_STATUS_OUT_FOR_DELIVERY => 'Out for Delivery',
		TRACK_STATUS_DELIVERED        => 'Delivered',
		TRACK_STATUS_CANCELLED        => 'Cancelled',
	];
	return $labels[$status] ?? $status;
}

/**
 * CSS class for status pill colors.
 */
function track_status_class(string $status): string
{
	$classes = [
		TRACK_STATUS_PENDING          => 'track-pending',
		TRACK_STATUS_ACCEPTED         => 'track-accepted',
		TRACK_STATUS_PREPARING        => 'track-preparing',
		TRACK_STATUS_READY            => 'track-ready',
		TRACK_STATUS_ASSIGNED         => 'track-assigned',
		TRACK_STATUS_OUT_FOR_DELIVERY => 'track-delivery',
		TRACK_STATUS_DELIVERED        => 'track-delivered',
		TRACK_STATUS_CANCELLED        => 'track-cancelled',
	];
	return $classes[$status] ?? 'track-pending';
}

/**
 * Icon (emoji) for each status — used in timeline.
 */
function track_status_icon(string $status): string
{
	$icons = [
		TRACK_STATUS_PENDING          => 'receipt',
		TRACK_STATUS_ACCEPTED         => 'check-circle',
		TRACK_STATUS_PREPARING        => 'chef-hat',
		TRACK_STATUS_READY            => 'bag',
		TRACK_STATUS_ASSIGNED         => 'user',
		TRACK_STATUS_OUT_FOR_DELIVERY => 'bike',
		TRACK_STATUS_DELIVERED        => 'home',
		TRACK_STATUS_CANCELLED        => 'x-circle',
	];
	return $icons[$status] ?? 'circle';
}

/**
 * Generate a 6-character tracking PIN (alphanumeric, easy to share).
 */
function track_generate_pin(): string
{
	$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
	$pin = '';
	for ($i = 0; $i < 6; $i++) {
		$pin .= $chars[random_int(0, strlen($chars) - 1)];
	}
	return $pin;
}

/**
 * Get the next valid statuses from the current status.
 * Used to validate transitions and show available actions in UI.
 */
function track_next_statuses(string $current): array
{
	$transitions = [
		TRACK_STATUS_PENDING          => [TRACK_STATUS_ACCEPTED, TRACK_STATUS_CANCELLED],
		TRACK_STATUS_ACCEPTED         => [TRACK_STATUS_PREPARING, TRACK_STATUS_CANCELLED],
		TRACK_STATUS_PREPARING        => [TRACK_STATUS_READY],
		TRACK_STATUS_READY            => [TRACK_STATUS_ASSIGNED, TRACK_STATUS_CANCELLED],
		TRACK_STATUS_ASSIGNED         => [TRACK_STATUS_OUT_FOR_DELIVERY, TRACK_STATUS_CANCELLED],
		TRACK_STATUS_OUT_FOR_DELIVERY => [TRACK_STATUS_DELIVERED],
		TRACK_STATUS_DELIVERED        => [],
		TRACK_STATUS_CANCELLED        => [],
	];
	return $transitions[$current] ?? [];
}

// ============================================================================
// CHILD (Order_Tracking_Item) STATUS CONSTANTS AND LOGIC
//
// Order_Tracking_Item.Item_status ENUM values (per migration_v4_tracking.sql):
//   'Pending', 'Accepted', 'Preparing', 'Ready for Pickup', 'Completed'
//
// Kitchen Staff advance child rows through these statuses.
// The parent Order_Tracking.Order_status is DERIVED from the aggregate of all
// child statuses — kitchen staff never set the parent status directly.
// ============================================================================

// Child status constants (Item_status column values)
const TRACK_ITEM_PENDING    = 'Pending';
const TRACK_ITEM_ACCEPTED   = 'Accepted';
const TRACK_ITEM_PREPARING  = 'Preparing';
const TRACK_ITEM_READY      = 'Ready for Pickup';
const TRACK_ITEM_COMPLETED  = 'Completed';

// Child statuses in flow order
const TRACK_ITEM_FLOW = [
	TRACK_ITEM_PENDING,
	TRACK_ITEM_ACCEPTED,
	TRACK_ITEM_PREPARING,
	TRACK_ITEM_READY,
	TRACK_ITEM_COMPLETED,
];

/**
 * Get the next valid child statuses from the current child status.
 * Used to validate per-restaurant child transitions.
 *
 * Flow: Pending → Accepted → Preparing → Ready for Pickup → Completed
 *
 * @param string $current  Current Item_status value
 * @return array           Array of valid next statuses
 */
function track_item_next_statuses(string $current): array
{
	$transitions = [
		TRACK_ITEM_PENDING   => [TRACK_ITEM_ACCEPTED],
		TRACK_ITEM_ACCEPTED  => [TRACK_ITEM_PREPARING],
		TRACK_ITEM_PREPARING => [TRACK_ITEM_READY],
		TRACK_ITEM_READY     => [TRACK_ITEM_COMPLETED],
		TRACK_ITEM_COMPLETED => [],
	];
	return $transitions[$current] ?? [];
}

/**
 * Derive the parent Order_Tracking.Order_status from the aggregate of all
 * child Order_Tracking_Item.Item_status values.
 *
 * Derivation rules (evaluated in priority order):
 *   1. If ALL children are 'Completed'           → parent stays at current status (Completed is set after delivery)
 *   2. If ALL children are 'Ready for Pickup'     → parent becomes 'Ready for Pickup'
 *   3. If ANY child is 'Preparing'                → parent becomes 'Preparing'
 *   4. If ALL children are 'Accepted'             → parent becomes 'Accepted'
 *   5. If ANY child is 'Pending'                  → parent stays/becomes 'Pending'
 *
 * Special handling:
 *   - If the parent is already in a delivery-phase status (Assigned, Out for Delivery,
 *     Delivered, Cancelled), the children should NOT override it. Those statuses are
 *     controlled by Manager/Courier, not by kitchen derivation.
 *   - 'Completed' child status is set by the system when the parent transitions to
 *     'Delivered'. It is a terminal child state and does not drive derivation.
 *
 * @param array  $childStatuses  Array of Item_status strings from all child rows
 * @param string $currentParent  Current parent Order_status
 * @return string                The derived parent Order_status (or current if no change needed)
 */
function track_derive_parent_status(array $childStatuses, string $currentParent): string
{
	// If parent is in a delivery/courier/terminal phase, children do not override it.
	$parentProtected = [
		TRACK_STATUS_ASSIGNED,
		TRACK_STATUS_OUT_FOR_DELIVERY,
		TRACK_STATUS_DELIVERED,
		TRACK_STATUS_CANCELLED,
	];
	if (in_array($currentParent, $parentProtected, true)) {
		return $currentParent;
	}

	// If there are no children (shouldn't happen, but defensive), keep current.
	if (empty($childStatuses)) {
		return $currentParent;
	}

	// Count statuses (treat 'Completed' same as 'Ready for Pickup' for derivation,
	// since Completed is the post-delivery terminal child state).
	$effective = [];
	foreach ($childStatuses as $s) {
		$effective[] = ($s === TRACK_ITEM_COMPLETED) ? TRACK_ITEM_READY : $s;
	}

	$allReady     = !in_array(TRACK_ITEM_PENDING, $effective, true)
		&& !in_array(TRACK_ITEM_ACCEPTED, $effective, true)
		&& !in_array(TRACK_ITEM_PREPARING, $effective, true);
	$anyPreparing = in_array(TRACK_ITEM_PREPARING, $effective, true);
	$allAccepted  = !in_array(TRACK_ITEM_PENDING, $effective, true)
		&& !in_array(TRACK_ITEM_PREPARING, $effective, true)
		&& !in_array(TRACK_ITEM_READY, $effective, true);
	$anyPending   = in_array(TRACK_ITEM_PENDING, $effective, true);

	if ($allReady) {
		return TRACK_STATUS_READY;
	}
	if ($anyPreparing) {
		return TRACK_STATUS_PREPARING;
	}
	if ($allAccepted) {
		return TRACK_STATUS_ACCEPTED;
	}
	if ($anyPending) {
		return TRACK_STATUS_PENDING;
	}

	// Fallback: keep current status.
	return $currentParent;
}

/**
 * Check whether a parent status transition is valid considering the child states.
 *
 * This is used when Manager wants to assign a courier (→ Assigned).
 * The parent must be 'Ready for Pickup' (all children ready) before assignment.
 *
 * @param string $newParentStatus  The requested new parent status
 * @param array  $childStatuses    Array of Item_status strings from all child rows
 * @return bool                     True if the transition is allowed
 */
function track_can_advance_parent(string $newParentStatus, array $childStatuses): bool
{
	// Manager can only assign courier when ALL children are Ready for Pickup (or Completed).
	if ($newParentStatus === TRACK_STATUS_ASSIGNED) {
		foreach ($childStatuses as $s) {
			if ($s !== TRACK_ITEM_READY && $s !== TRACK_ITEM_COMPLETED) {
				return false;
			}
		}
		return true;
	}

	// Cancel is always allowed (checked separately for authorization).
	if ($newParentStatus === TRACK_STATUS_CANCELLED) {
		return true;
	}

	// Other parent transitions (Out for Delivery, Delivered) are courier-driven
	// and checked against the parent's current status, not child states.
	return true;
}
