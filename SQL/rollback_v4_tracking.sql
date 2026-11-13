-- ============================================================================
-- CafeDash v4 ROLLBACK — undoes migration_v4_tracking.sql
--
-- SAFETY: This rollback only removes the NEW tables and NEW columns added by
--         migration_v4. It does NOT touch any existing table data.
--
-- WARNING: Running this will DELETE all tracking data (Order_Tracking,
--          Order_Tracking_Item, Order_Status_Log, Delivery_Location rows)
--          and DROP the Latitude/Longitude/Assigned_Restaurant_ID columns.
--          Make sure you have a backup before running.
--
-- Run AFTER creating a backup:
--   mysqldump -uroot cafedash_db > SQL/backup_before_rollback_v4.sql
--
--   C:\xampp\mysql\bin\mysql.exe -uroot cafedash_db < SQL/rollback_v4_tracking.sql
--   Or import via phpMyAdmin (cafedash_db -> Import)
-- ============================================================================

USE cafedash_db;

-- ---------------------------------------------------------------------------
-- 1. Drop new tables (in reverse FK dependency order)
-- ---------------------------------------------------------------------------

-- Delivery_Location (depends on Payment, Staff)
DROP TABLE IF EXISTS Delivery_Location;

-- Order_Status_Log (depends on Payment, Restaurant, Staff)
DROP TABLE IF EXISTS Order_Status_Log;

-- Order_Tracking_Item (depends on Order_Tracking, Payment, Restaurant, Staff)
DROP TABLE IF EXISTS Order_Tracking_Item;

-- Order_Tracking (depends on Payment, User, Restaurant, Staff, User_Address)
DROP TABLE IF EXISTS Order_Tracking;

-- ---------------------------------------------------------------------------
-- 2. Remove the foreign key constraint and index from Staff
--    (Guarded: only drops if they exist)
-- ---------------------------------------------------------------------------
SET @fk_exists = (
	SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
	WHERE TABLE_SCHEMA = DATABASE()
	  AND TABLE_NAME = 'Staff'
	  AND COLUMN_NAME = 'Assigned_Restaurant_ID'
	  AND CONSTRAINT_NAME = 'fk_staff_restaurant'
);
SET @sql = IF(@fk_exists > 0,
	'ALTER TABLE Staff DROP FOREIGN KEY fk_staff_restaurant',
	'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (
	SELECT COUNT(*) FROM information_schema.STATISTICS
	WHERE TABLE_SCHEMA = DATABASE()
	  AND TABLE_NAME = 'Staff'
	  AND INDEX_NAME = 'idx_staff_restaurant'
);
SET @sql = IF(@idx_exists > 0,
	'ALTER TABLE Staff DROP INDEX idx_staff_restaurant',
	'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 3. Remove added columns from Staff
-- ---------------------------------------------------------------------------
ALTER TABLE Staff
	DROP COLUMN IF EXISTS Assigned_Restaurant_ID;

-- ---------------------------------------------------------------------------
-- 4. Remove added columns from User_Address
-- ---------------------------------------------------------------------------
ALTER TABLE User_Address
	DROP COLUMN IF EXISTS Latitude,
	DROP COLUMN IF EXISTS Longitude;

-- ---------------------------------------------------------------------------
-- 5. Remove added columns from Restaurant
-- ---------------------------------------------------------------------------
ALTER TABLE Restaurant
	DROP COLUMN IF EXISTS Latitude,
	DROP COLUMN IF EXISTS Longitude;

-- ============================================================================
-- ROLLBACK COMPLETE
-- All new tables and columns from migration_v4 have been removed.
-- Existing data is untouched.
-- ============================================================================
