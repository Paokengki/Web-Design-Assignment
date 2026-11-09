-- CafeDash v3 migration — run after migration_v2.sql.
-- Additive only: the old pages keep working. Safe to run more than once (MariaDB 10.4 / XAMPP).
--   C:\xampp\mysql\bin\mysql.exe -uroot cafedash_db < SQL/migration_v3.sql

USE cafedash_db;

-- Checkout can now apply a promo code: keep what was discounted on each payment.
ALTER TABLE Payment
	ADD COLUMN IF NOT EXISTS Discount_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER SST_amount,
	ADD COLUMN IF NOT EXISTS Promo_ID INT NULL AFTER Discount_amount;

-- Feedback inbox: unread / read.
ALTER TABLE contact_us
	ADD COLUMN IF NOT EXISTS Is_read TINYINT(1) NOT NULL DEFAULT 0,
	ADD COLUMN IF NOT EXISTS User_ID INT NULL;

-- One redemption per customer per code is enforced in code; this index keeps the lookup fast.
CREATE INDEX IF NOT EXISTS idx_redemption_user_promo ON Promotion_Redemption (User_ID, Promo_ID);
