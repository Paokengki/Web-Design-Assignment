<?php
/**
 * CafeDash — One-time backfill script for Order_Tracking destination coordinates.
 *
 * Problem:
 *   Order_Tracking.Dest_Latitude and Dest_Longitude are NULL for existing orders
 *   because they were created before the geocoding feature was added.
 *   bootstrap.php copies User_Address.Latitude/Longitude at order creation time,
 *   but older orders have NULL because the address coordinates were NULL then.
 *
 * Solution:
 *   This script reads the latest Latitude/Longitude from the linked User_Address
 *   (via Delivery_Address_ID) and writes them to Order_Tracking.
 *   It only updates rows where Dest_Latitude IS NULL.
 *   It only processes non-terminal orders (not Delivered/Cancelled).
 *
 * Safety:
 *   - Read-only inspection first, then asks for --apply flag to actually write.
 *   - Uses prepared statements.
 *   - Does NOT modify User_Address, Restaurant, Payment, or Stripe data.
 *   - Does NOT change schema.
 *   - Prints a detailed report before and after.
 *
 * Usage:
 *   php tracking/backfill_coordinates.php          # Dry run — shows what would be updated
 *   php tracking/backfill_coordinates.php --apply  # Actually updates the database
 *
 * Run from the project root:
 *   cd "D:/Assignment/Mini Project"
 *   php tracking/backfill_coordinates.php
 */

// Bootstrap the app (provides db(), q(), q_one(), q_all()).
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/config.php';

$isApply = in_array('--apply', $argv ?? [], true);

echo "CafeDash — Order Tracking Coordinate Backfill\n";
echo str_repeat('=', 60) . "\n\n";

if ($isApply) {
	echo "** APPLY MODE — Database will be updated. **\n\n";
} else {
	echo "** DRY RUN — No changes will be made. Use --apply to write. **\n\n";
}

// ── Step 1: Find Order_Tracking rows with NULL Dest_Latitude ────────────────
$rows = q_all(
	'SELECT t.Tracking_ID, t.Payment_ID, t.User_ID, t.Delivery_Address_ID,
	        t.Order_status, t.Dest_Latitude, t.Dest_Longitude,
	        t.Created_at,
	        ua.Latitude AS Addr_Latitude, ua.Longitude AS Addr_Longitude,
	        ua.Line1, ua.City, ua.Postcode, ua.State
	 FROM Order_Tracking t
	 LEFT JOIN User_Address ua ON ua.Address_ID = t.Delivery_Address_ID
	 WHERE t.Dest_Latitude IS NULL
	 ORDER BY t.Tracking_ID'
);

if (empty($rows)) {
	echo "No tracking rows with NULL Dest_Latitude found. Nothing to do.\n";
	exit(0);
}

echo "Found " . count($rows) . " tracking row(s) with NULL Dest_Latitude:\n\n";

// ── Step 2: Analyze each row ────────────────────────────────────────────────
$updatable = [];
$skippedTerminal = [];
$skippedNoAddress = [];
$skippedNoCoords = [];

foreach ($rows as $r) {
	$trackingId = (int) $r['Tracking_ID'];
	$status = $r['Order_status'];
	$isTerminal = in_array($status, TRACK_STATUS_TERMINAL);
	$hasAddrCoords = ($r['Addr_Latitude'] !== null && $r['Addr_Longitude'] !== null);
	$hasAddressId = $r['Delivery_Address_ID'] !== null;

	// Display info (no full address text for privacy).
	$addrSummary = $r['City'] ? ($r['City'] . ', ' . ($r['State'] ?? '')) : '(no address)';

	echo sprintf(
		"  Tracking_ID=%d  Payment_ID=%s  Status=%s  Address_ID=%s  AddrCoords=%s  %s\n",
		$trackingId,
		$r['Payment_ID'],
		$status,
		$r['Delivery_Address_ID'] ?? 'NULL',
		$hasAddrCoords ? $r['Addr_Latitude'] . ',' . $r['Addr_Longitude'] : 'NULL',
		$addrSummary
	);

	if ($isTerminal) {
		$skippedTerminal[] = $r;
		echo "    → SKIP: terminal status ({$status})\n";
	} elseif (!$hasAddressId) {
		$skippedNoAddress[] = $r;
		echo "    → SKIP: no Delivery_Address_ID linked\n";
	} elseif (!$hasAddrCoords) {
		$skippedNoCoords[] = $r;
		echo "    → SKIP: linked address has NULL coordinates\n";
	} else {
		$updatable[] = $r;
		echo "    → CAN UPDATE: lat={$r['Addr_Latitude']}, lng={$r['Addr_Longitude']}\n";
	}
}

echo "\n" . str_repeat('-', 60) . "\n";
echo "Summary:\n";
echo "  Total with NULL Dest_Latitude: " . count($rows) . "\n";
echo "  Can update:                    " . count($updatable) . "\n";
echo "  Skipped (terminal):            " . count($skippedTerminal) . "\n";
echo "  Skipped (no address linked):   " . count($skippedNoAddress) . "\n";
echo "  Skipped (no address coords):   " . count($skippedNoCoords) . "\n\n";

// ── Step 3: Apply updates if --apply flag is set ────────────────────────────
if ($isApply && !empty($updatable)) {
	echo "Applying updates...\n";

	$updated = 0;
	$failed = 0;

	foreach ($updatable as $r) {
		$trackingId = (int) $r['Tracking_ID'];
		$lat = (float) $r['Addr_Latitude'];
		$lng = (float) $r['Addr_Longitude'];

		try {
			q(
				'UPDATE Order_Tracking SET Dest_Latitude = ?, Dest_Longitude = ? WHERE Tracking_ID = ?',
				[$lat, $lng, $trackingId]
			);
			echo "  ✓ Updated Tracking_ID={$trackingId}: lat={$lat}, lng={$lng}\n";
			$updated++;
		} catch (Throwable $e) {
			echo "  ✗ FAILED Tracking_ID={$trackingId}: " . $e->getMessage() . "\n";
			$failed++;
		}
	}

	echo "\nDone: {$updated} updated, {$failed} failed.\n";
} elseif ($isApply && empty($updatable)) {
	echo "No rows eligible for update. Nothing to apply.\n";
} else {
	echo "Dry run complete. Run with --apply to update the database.\n";
}

echo "\nFinished.\n";
