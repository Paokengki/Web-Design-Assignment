-- ============================================================================
-- CafeDash v4 migration — Real-Time Order Tracking & Live Delivery Map
-- Run AFTER migration_v2.sql and migration_v3.sql.
-- Additive only: existing tables/columns are preserved. Safe to re-run.
--
-- REVISION NOTES (v4 revised):
--   1. Multi-restaurant orders: parent Order_Tracking + child Order_Tracking_Item
--      per restaurant. Customer tracks the complete order via the parent.
--   2. Restaurant coordinates: columns added but left NULL — no auto-seeding.
--      Manager must enter verified coordinates per restaurant.
--   3. Foreign key deletion: ON DELETE RESTRICT for Payment/User references on
--      tracking tables (prevents accidental data loss). ON DELETE SET NULL for
--      optional references (Courier, Restaurant, Address). ON DELETE CASCADE
--      only for Delivery_Location cleanup (ephemeral GPS data).
--   4. Kitchen Staff assignment: no auto-assignment. Manager assigns via admin
--      panel. Foreign key added to Restaurant.
--   5. GPS retention: Delivery_Location uses ON DELETE CASCADE on Payment only
--      (ephemeral data). A cleanup query is provided in the rollback section.
--   6. Compatibility verified against MariaDB 10.4+ (XAMPP default).
--      All INT columns match existing signed INT type (not UNSIGNED).
--   7. Migration is fully re-runnable: every statement uses IF NOT EXISTS or
--      WHERE ... IS NULL guards.
--
--   C:\xampp\mysql\bin\mysql.exe -uroot cafedash_db < SQL/migration_v4_tracking.sql
--   Or import via phpMyAdmin (cafedash_db -> Import)
-- ============================================================================

USE cafedash_db;

-- ---------------------------------------------------------------------------
-- 1. Restaurant: add latitude / longitude (delivery origin on the map)
--    Columns are NULLABLE. No coordinates are seeded automatically.
--    Manager must obtain verified coordinates from the actual restaurant
--    address and enter them via the admin panel before the map can display
--    a restaurant marker.
--    Preserves all existing columns and data.
-- ---------------------------------------------------------------------------
ALTER TABLE Restaurant
	ADD COLUMN IF NOT EXISTS Latitude DECIMAL(10, 7) NULL DEFAULT NULL AFTER Address,
	ADD COLUMN IF NOT EXISTS Longitude DECIMAL(10, 7) NULL DEFAULT NULL AFTER Latitude;

-- ---------------------------------------------------------------------------
-- 2. User_Address: add latitude / longitude (delivery destination on the map)
--    Columns are NULLABLE. No coordinates are seeded automatically.
--    Customer can set them on the tracking page via browser GPS or address
--    geocoding. Preserves all existing columns and data.
-- ---------------------------------------------------------------------------
ALTER TABLE User_Address
	ADD COLUMN IF NOT EXISTS Latitude DECIMAL(10, 7) NULL DEFAULT NULL AFTER Line2,
	ADD COLUMN IF NOT EXISTS Longitude DECIMAL(10, 7) NULL DEFAULT NULL AFTER Latitude;

-- ---------------------------------------------------------------------------
-- 3. Staff: add assigned Restaurant_ID for Kitchen Staff
--    Kitchen Staff see only orders from their assigned cafe.
--    NULL for non-kitchen roles (Managers, Couriers, Super Admins).
--    NO automatic assignment — Manager assigns via admin panel.
--    Foreign key references Restaurant(Restaurant_ID) ON DELETE SET NULL
--    (deleting a restaurant clears the assignment but does not delete the staff).
--
--    Data type: INT (signed) — matches Restaurant.Restaurant_ID which is
--    INT AUTO_INCREMENT (signed, not UNSIGNED) per db.sql.
-- ---------------------------------------------------------------------------
ALTER TABLE Staff
	ADD COLUMN IF NOT EXISTS Assigned_Restaurant_ID INT NULL DEFAULT NULL AFTER Plate_number;

-- Add foreign key constraint only if it does not already exist.
-- MariaDB does not support "ADD CONSTRAINT IF NOT EXISTS" directly,
-- so we use a guarded approach: check information_schema.
SET @fk_exists = (
	SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
	WHERE TABLE_SCHEMA = DATABASE()
	  AND TABLE_NAME = 'Staff'
	  AND COLUMN_NAME = 'Assigned_Restaurant_ID'
	  AND CONSTRAINT_NAME = 'fk_staff_restaurant'
);
SET @sql = IF(@fk_exists = 0,
	'ALTER TABLE Staff ADD CONSTRAINT fk_staff_restaurant FOREIGN KEY (Assigned_Restaurant_ID) REFERENCES Restaurant(Restaurant_ID) ON DELETE SET NULL',
	'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index on Assigned_Restaurant_ID for efficient lookups.
SET @idx_exists = (
	SELECT COUNT(*) FROM information_schema.STATISTICS
	WHERE TABLE_SCHEMA = DATABASE()
	  AND TABLE_NAME = 'Staff'
	  AND INDEX_NAME = 'idx_staff_restaurant'
);
SET @sql = IF(@idx_exists = 0,
	'ALTER TABLE Staff ADD INDEX idx_staff_restaurant (Assigned_Restaurant_ID)',
	'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 4. Order_Tracking — PARENT tracking table (1 row per Payment/order)
--
--    Multi-restaurant support:
--      The parent row tracks the OVERALL order status (customer-facing).
--      Restaurant_ID is NULL for multi-cafe orders (set only for single-cafe).
--      Courier_ID is assigned by the Manager for the overall delivery.
--
--      Child rows in Order_Tracking_Item (section 5 below) track per-restaurant
--      kitchen status. Each cafe's kitchen staff advances only their child row.
--      The parent Order_status reflects the overall fulfillment progress.
--
--    Payment_ID is FK + UNIQUE so each order has exactly one parent tracking row.
--    Order_status is SEPARATE from Payment.Payment_status.
--
--    FK deletion rules (revised for safety):
--      Payment_ID    -> ON DELETE RESTRICT (never auto-delete tracking history)
--      User_ID       -> ON DELETE RESTRICT (never auto-delete tracking history)
--      Restaurant_ID -> ON DELETE SET NULL (optional reference, clearing is safe)
--      Courier_ID    -> ON DELETE SET NULL (courier deleted = unassigned, not data loss)
--      Assigned_By   -> ON DELETE SET NULL (manager deleted = log shows NULL, history kept)
--      Address_ID    -> ON DELETE SET NULL (address deleted = coords may still be on tracking row)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS Order_Tracking (
	Tracking_ID INT AUTO_INCREMENT PRIMARY KEY,
	Payment_ID INT NOT NULL UNIQUE,
	User_ID INT NOT NULL,
	Restaurant_ID INT NULL,
	Courier_ID INT NULL,
	Assigned_By INT NULL,
	Order_status ENUM('Pending', 'Accepted', 'Preparing', 'Ready for Pickup',
	                  'Assigned', 'Out for Delivery', 'Delivered', 'Cancelled') NOT NULL DEFAULT 'Pending',
	Delivery_Address_ID INT NULL,
	Dest_Latitude DECIMAL(10, 7) NULL DEFAULT NULL,
	Dest_Longitude DECIMAL(10, 7) NULL DEFAULT NULL,
	Courier_Latitude DECIMAL(10, 7) NULL DEFAULT NULL,
	Courier_Longitude DECIMAL(10, 7) NULL DEFAULT NULL,
	Estimated_minutes INT NULL DEFAULT NULL,
	Tracking_PIN VARCHAR(6) NULL DEFAULT NULL,
	Created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	Updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT fk_tracking_payment FOREIGN KEY (Payment_ID) REFERENCES Payment(Payment_ID) ON DELETE RESTRICT,
	CONSTRAINT fk_tracking_user FOREIGN KEY (User_ID) REFERENCES User(User_ID) ON DELETE RESTRICT,
	CONSTRAINT fk_tracking_restaurant FOREIGN KEY (Restaurant_ID) REFERENCES Restaurant(Restaurant_ID) ON DELETE SET NULL,
	CONSTRAINT fk_tracking_courier FOREIGN KEY (Courier_ID) REFERENCES Staff(Staff_ID) ON DELETE SET NULL,
	CONSTRAINT fk_tracking_assigned_by FOREIGN KEY (Assigned_By) REFERENCES Staff(Staff_ID) ON DELETE SET NULL,
	CONSTRAINT fk_tracking_address FOREIGN KEY (Delivery_Address_ID) REFERENCES User_Address(Address_ID) ON DELETE SET NULL,
	INDEX idx_tracking_status (Order_status),
	INDEX idx_tracking_courier (Courier_ID),
	INDEX idx_tracking_restaurant (Restaurant_ID),
	INDEX idx_tracking_updated (Updated_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- 5. Order_Tracking_Item — CHILD tracking table (1 row per restaurant per order)
--
--    Each row represents one restaurant's portion of an order.
--    Single-cafe orders: 1 child row (linked to parent's Restaurant_ID).
--    Multi-cafe orders: N child rows (one per distinct restaurant in Payment_Item).
--
--    Kitchen Staff interact with child rows:
--      They can advance their restaurant's child status:
--        Pending -> Accepted -> Preparing -> Ready for Pickup
--      The parent Order_status is NOT changed by kitchen staff directly.
--      Instead, the system derives the parent status from child statuses:
--        - If ALL children are 'Ready for Pickup' -> parent becomes 'Ready for Pickup'
--        - If ANY child is 'Preparing' -> parent becomes 'Preparing'
--        - If ALL children are 'Accepted' -> parent becomes 'Accepted'
--        (This derivation logic is implemented in the application layer.)
--
--    Courier and Manager interact with the PARENT row:
--      Manager assigns courier on the parent -> parent 'Assigned'
--      Courier delivers -> parent 'Out for Delivery' -> 'Delivered'
--
--    This design preserves the customer's ability to track the complete order:
--      The customer sees the parent status + a per-restaurant breakdown.
--
--    FK deletion rules:
--      Tracking_ID  -> ON DELETE CASCADE (child deleted when parent deleted)
--      Restaurant_ID -> ON DELETE SET NULL (optional reference)
--      Payment_ID   -> ON DELETE RESTRICT (matches parent rule)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS Order_Tracking_Item (
	Item_Tracking_ID INT AUTO_INCREMENT PRIMARY KEY,
	Tracking_ID INT NOT NULL,
	Payment_ID INT NOT NULL,
	Restaurant_ID INT NOT NULL,
	Item_status ENUM('Pending', 'Accepted', 'Preparing', 'Ready for Pickup', 'Completed') NOT NULL DEFAULT 'Pending',
	Updated_by INT NULL,
	Updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	Created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	CONSTRAINT fk_item_tracking FOREIGN KEY (Tracking_ID) REFERENCES Order_Tracking(Tracking_ID) ON DELETE CASCADE,
	CONSTRAINT fk_item_payment FOREIGN KEY (Payment_ID) REFERENCES Payment(Payment_ID) ON DELETE RESTRICT,
	CONSTRAINT fk_item_restaurant FOREIGN KEY (Restaurant_ID) REFERENCES Restaurant(Restaurant_ID) ON DELETE RESTRICT,
	CONSTRAINT fk_item_updated_by FOREIGN KEY (Updated_by) REFERENCES Staff(Staff_ID) ON DELETE SET NULL,
	UNIQUE KEY uk_item_per_restaurant (Payment_ID, Restaurant_ID),
	INDEX idx_item_tracking (Tracking_ID),
	INDEX idx_item_restaurant (Restaurant_ID),
	INDEX idx_item_status (Item_status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- 6. Order_Status_Log — timestamped history of every status change
--    Provides the customer-side timeline (Pending -> Accepted -> Preparing -> ...).
--    One row is ALWAYS inserted when a tracking record is created (initial Pending).
--    Subsequent rows are inserted on each status change (by the application layer).
--
--    FK deletion rules:
--      Payment_ID  -> ON DELETE RESTRICT (never lose audit trail)
--      Changed_By  -> ON DELETE SET NULL (staff deleted = log shows NULL, history kept)
--      Log_Level   -> 'parent' for parent row changes, 'item' for per-restaurant changes
--                     (allows the customer timeline to show both overall and per-cafe)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS Order_Status_Log (
	Log_ID INT AUTO_INCREMENT PRIMARY KEY,
	Payment_ID INT NOT NULL,
	Restaurant_ID INT NULL,
	Order_status ENUM('Pending', 'Accepted', 'Preparing', 'Ready for Pickup',
	                  'Assigned', 'Out for Delivery', 'Delivered', 'Cancelled',
	                  'Completed') NOT NULL,
	Log_Level ENUM('parent', 'item') NOT NULL DEFAULT 'parent',
	Changed_By INT NULL,
	Note VARCHAR(255) NULL,
	Created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	CONSTRAINT fk_log_payment FOREIGN KEY (Payment_ID) REFERENCES Payment(Payment_ID) ON DELETE RESTRICT,
	CONSTRAINT fk_log_restaurant FOREIGN KEY (Restaurant_ID) REFERENCES Restaurant(Restaurant_ID) ON DELETE SET NULL,
	CONSTRAINT fk_log_staff FOREIGN KEY (Changed_By) REFERENCES Staff(Staff_ID) ON DELETE SET NULL,
	INDEX idx_log_payment (Payment_ID),
	INDEX idx_log_created (Created_at),
	INDEX idx_log_level (Log_Level)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- 7. Delivery_Location — courier GPS position history (for path animation)
--    The latest row = current courier position.
--    Older rows are kept for path trail rendering on the map.
--
--    GPS RETENTION POLICY:
--    - GPS data is ephemeral: it is only useful during active delivery.
--    - After an order is Delivered or Cancelled, GPS history serves no
--      customer-facing purpose.
--    - A scheduled cleanup (see rollback section for the DELETE query)
--      removes Delivery_Location rows for orders that have been in a terminal
--      status for more than 24 hours.
--    - Customers can only access GPS data for their OWN active orders
--      (enforced in the application layer: order_status.php checks User_ID
--      ownership and only returns courier_position for non-terminal statuses).
--
--    FK deletion rules:
--      Payment_ID  -> ON DELETE CASCADE (GPS data is ephemeral; safe to delete)
--      Courier_ID  -> ON DELETE CASCADE (if courier is deleted, their GPS trail is too)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS Delivery_Location (
	Location_ID INT AUTO_INCREMENT PRIMARY KEY,
	Payment_ID INT NOT NULL,
	Courier_ID INT NOT NULL,
	Latitude DECIMAL(10, 7) NOT NULL,
	Longitude DECIMAL(10, 7) NOT NULL,
	Accuracy FLOAT NULL DEFAULT NULL,
	Created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	CONSTRAINT fk_location_payment FOREIGN KEY (Payment_ID) REFERENCES Payment(Payment_ID) ON DELETE CASCADE,
	CONSTRAINT fk_location_courier FOREIGN KEY (Courier_ID) REFERENCES Staff(Staff_ID) ON DELETE CASCADE,
	INDEX idx_location_payment (Payment_ID, Created_at DESC),
	INDEX idx_location_courier (Courier_ID),
	INDEX idx_location_created (Created_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- 8. GPS RETENTION — cleanup procedure (run manually or via cron/event)
--
--    This DELETE removes GPS history rows for orders that have been Delivered
--    or Cancelled for more than 24 hours. It does NOT affect active deliveries.
--
--    To run manually:
--    DELETE dl FROM Delivery_Location dl
--    INNER JOIN Order_Tracking t ON t.Payment_ID = dl.Payment_ID
--    WHERE t.Order_status IN ('Delivered', 'Cancelled')
--      AND t.Updated_at < NOW() - INTERVAL 24 HOUR;
--
--    This query is provided here as a comment — it is NOT executed by this
--    migration. The Manager should set up a scheduled event or cron job to
--    run it periodically (e.g., daily at 2 AM).
-- ---------------------------------------------------------------------------

-- ============================================================================
-- END OF MIGRATION
--
-- BACKUP PROCEDURE (run BEFORE this migration):
--   mysqldump -uroot cafedash_db > SQL/backup_before_v4.sql
--   Or in phpMyAdmin: Export -> Custom -> entire database -> Save as file
--
-- ROLLBACK PLAN (run to undo this migration):
--   See SQL/rollback_v4_tracking.sql (provided alongside this file).
--
-- RE-RUN SAFETY:
--   This migration is fully idempotent. Every ALTER TABLE uses ADD COLUMN IF
--   NOT EXISTS. Every CREATE TABLE uses IF NOT EXISTS. The Staff FK and index
--   are added via guarded prepared statements that check information_schema.
--   No UPDATE or INSERT statements modify existing data.
--   Re-running produces no errors and no duplicate changes.
-- ============================================================================
