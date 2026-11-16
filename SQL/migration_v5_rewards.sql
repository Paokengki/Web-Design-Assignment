-- ============================================================================
-- CafeDash v5 migration — Loyalty rewards: spend points on vouchers
-- Run AFTER migration_v2, v3 and v4. Additive only; safe to re-run (MariaDB 10.4 / XAMPP).
--
--   C:\xampp\mysql\bin\mysql.exe -uroot cafedash_db < SQL/migration_v5_rewards.sql
--   Or phpMyAdmin: cafedash_db -> Import
--
-- HOW POINTS WORK
--   Earned  = 1 point per RM1 of SUCCEEDED payments (calculated from Payment, as before).
--   Spent   = SUM(User_Voucher.Points_spent).
--   Balance = Earned - Spent.        Tier (Bronze…Platinum) uses Earned, so spending never lowers it.
--
-- TWO KINDS OF VOUCHER (same Promotion table)
--   Points_cost = 0  -> free code: every member sees it at checkout (WELCOME15, LATTE20 …).
--   Points_cost > 0  -> reward: a member must redeem it with points first; it then appears
--                       at checkout for that member only, until used or expired.
--
-- RELEASE SCHEDULE
--   No new column needed: Promotion.Starts_at is the release time
--   (now = released immediately, a future date/time = scheduled).
-- ============================================================================

USE cafedash_db;

-- 1. Voucher price in points + how many times one member may redeem it.
ALTER TABLE Promotion
	ADD COLUMN IF NOT EXISTS Points_cost INT NOT NULL DEFAULT 0 AFTER Min_spend,
	ADD COLUMN IF NOT EXISTS Per_user_limit INT NOT NULL DEFAULT 1 AFTER Points_cost;

-- 2. Vouchers a member has bought with points ("my vouchers").
--    One row per redemption. Used_payment_id stays NULL until the voucher is spent at checkout.
CREATE TABLE IF NOT EXISTS User_Voucher (
	User_Voucher_ID INT AUTO_INCREMENT PRIMARY KEY,
	User_ID INT NOT NULL,
	Promo_ID INT NOT NULL,
	Points_spent INT NOT NULL,
	Redeemed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	Used_at DATETIME NULL DEFAULT NULL,
	Used_payment_id INT NULL DEFAULT NULL,
	CONSTRAINT fk_uv_user FOREIGN KEY (User_ID) REFERENCES User(User_ID) ON DELETE CASCADE,
	CONSTRAINT fk_uv_promo FOREIGN KEY (Promo_ID) REFERENCES Promotion(Promo_ID) ON DELETE RESTRICT,
	CONSTRAINT fk_uv_payment FOREIGN KEY (Used_payment_id) REFERENCES Payment(Payment_ID) ON DELETE SET NULL,
	CONSTRAINT chk_uv_points CHECK (Points_spent >= 0),
	INDEX idx_uv_user (User_ID, Used_at),
	INDEX idx_uv_promo (Promo_ID)
) ENGINE=InnoDB;

-- 3. Demo rewards (skipped if the codes already exist).
INSERT IGNORE INTO Promotion (Code, Title, Description, Discount_percent, Max_redemptions, Min_spend, Points_cost, Per_user_limit, Starts_at, Expires_at) VALUES
('REWARD10',  'Bronze treat',     '10% off any order',                 10,  NULL, 0,  50,  3, NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 90 DAY),
('REWARD25',  'Silver saver',     '25% off orders above RM20',         25,  NULL, 20, 150, 2, NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 90 DAY),
('REWARD50',  'Gold half-price',  'Half price on your whole order',    50,  200,  30, 400, 1, NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 60 DAY),
('FREEMEAL',  'Platinum free meal', '100% off — this one is on us',    100, 50,   0,  900, 1, NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 60 DAY),
('COMINGSOON', 'Year-end special', '40% off, releasing soon',          40,  100,  25, 250, 1, NOW() + INTERVAL 7 DAY, NOW() + INTERVAL 45 DAY);
