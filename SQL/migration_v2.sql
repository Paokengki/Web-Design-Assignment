-- CafeDash v2 migration
-- Adds the tables/columns used by the new Customer Dashboard, Promo Manager and Staff Manager.
-- Safe to run more than once (MariaDB 10.4 / XAMPP). Existing data is kept.
-- Import in phpMyAdmin (cafedash_db -> Import) or:
--   C:\xampp\mysql\bin\mysql.exe -uroot cafedash_db < SQL/migration_v2.sql

USE cafedash_db;

-- ---------------------------------------------------------------------------
-- 1. Customer: sign-up date + soft delete
-- ---------------------------------------------------------------------------
ALTER TABLE User
	ADD COLUMN IF NOT EXISTS Created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	ADD COLUMN IF NOT EXISTS Deleted_at DATETIME NULL DEFAULT NULL;

-- ---------------------------------------------------------------------------
-- 2. Customer addresses (many per user)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS User_Address (
	Address_ID INT AUTO_INCREMENT PRIMARY KEY,
	User_ID INT NOT NULL,
	Label VARCHAR(40) NOT NULL DEFAULT 'Home',
	Recipient VARCHAR(255) NOT NULL,
	Phone VARCHAR(20) NOT NULL,
	Line1 VARCHAR(255) NOT NULL,
	Line2 VARCHAR(255) NULL,
	City VARCHAR(100) NOT NULL,
	Postcode VARCHAR(10) NOT NULL,
	State VARCHAR(100) NOT NULL,
	Is_default TINYINT(1) NOT NULL DEFAULT 0,
	Created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	Updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	INDEX idx_address_user (User_ID),
	CONSTRAINT fk_address_user FOREIGN KEY (User_ID) REFERENCES User(User_ID) ON DELETE CASCADE
);

-- Copy each user's old single Address into the new table once.
INSERT INTO User_Address (User_ID, Label, Recipient, Phone, Line1, City, Postcode, State, Is_default)
SELECT u.User_ID, 'Home', u.User_Name, COALESCE(u.Contain_number, '-'), u.Address, '-', '-', '-', 1
FROM User u
WHERE u.Address IS NOT NULL AND u.Address <> ''
  AND NOT EXISTS (SELECT 1 FROM User_Address a WHERE a.User_ID = u.User_ID);

-- ---------------------------------------------------------------------------
-- 3. Staff (replaces Admin + Driver for the new admin panel)
--    The old Admin / Driver tables are left untouched so old pages keep working.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS Staff (
	Staff_ID INT AUTO_INCREMENT PRIMARY KEY,
	Name VARCHAR(255) NOT NULL,
	Email VARCHAR(255) NULL UNIQUE,
	Phone VARCHAR(20) NULL,
	Password VARCHAR(255) NOT NULL,
	Role ENUM('Super Admin', 'Manager', 'Kitchen Staff', 'Courier') NOT NULL DEFAULT 'Kitchen Staff',
	Status ENUM('Active', 'Suspended', 'Deactivated') NOT NULL DEFAULT 'Active',
	Plate_number VARCHAR(50) NULL,
	Last_login_at DATETIME NULL,
	Created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	Updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	INDEX idx_staff_role (Role),
	INDEX idx_staff_status (Status),
	INDEX idx_staff_created (Created_at)
);

-- Existing admins become Super Admins (plain-text passwords are re-hashed on first login).
INSERT INTO Staff (Name, Email, Phone, Password, Role)
SELECT a.Name, a.Email, a.Contain_number, a.Password, 'Super Admin'
FROM Admin a
WHERE NOT EXISTS (SELECT 1 FROM Staff s WHERE s.Email = a.Email);

-- Existing drivers become Couriers (no login yet: random unusable password).
INSERT INTO Staff (Name, Plate_number, Password, Role)
SELECT d.Driver_Name, d.Plate_number, CONCAT('!disabled-', MD5(RAND())), 'Courier'
FROM Driver d
WHERE NOT EXISTS (SELECT 1 FROM Staff s WHERE s.Name = d.Driver_Name AND s.Role = 'Courier');

-- Demo staff. Password for all three: Staff@1234
INSERT IGNORE INTO Staff (Name, Email, Phone, Password, Role, Plate_number) VALUES
('Aina Rahman', 'aina.manager@cafedash.com', '012-5550101', '$2y$10$zd3ECCs7jugOg7J3uLpvPexWiKtyJndsAde9Elrp8lrTmZ.qmBFj.', 'Manager', NULL),
('Kenji Lim', 'kenji.kitchen@cafedash.com', '012-5550202', '$2y$10$zd3ECCs7jugOg7J3uLpvPexWiKtyJndsAde9Elrp8lrTmZ.qmBFj.', 'Kitchen Staff', NULL),
('Ravi Kumar', 'ravi.courier@cafedash.com', '012-5550303', '$2y$10$zd3ECCs7jugOg7J3uLpvPexWiKtyJndsAde9Elrp8lrTmZ.qmBFj.', 'Courier', 'WXY 1234');

-- ---------------------------------------------------------------------------
-- 4. Promotions / vouchers
--    Board column is derived from dates: Scheduled (not started), Active, Expired
--    (past expiry OR redemption limit reached). Is_active = 0 means "paused".
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS Promotion (
	Promo_ID INT AUTO_INCREMENT PRIMARY KEY,
	Code VARCHAR(20) NOT NULL UNIQUE,
	Title VARCHAR(120) NOT NULL,
	Description VARCHAR(255) NULL,
	Discount_percent DECIMAL(5, 2) NOT NULL,
	Max_redemptions INT NULL,            -- NULL = unlimited
	Redemption_count INT NOT NULL DEFAULT 0,
	Min_spend DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
	Starts_at DATETIME NOT NULL,
	Expires_at DATETIME NOT NULL,
	Is_active TINYINT(1) NOT NULL DEFAULT 1,
	Created_by INT NULL,
	Created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	Updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	INDEX idx_promo_dates (Starts_at, Expires_at),
	CONSTRAINT fk_promo_staff FOREIGN KEY (Created_by) REFERENCES Staff(Staff_ID) ON DELETE SET NULL,
	CONSTRAINT chk_promo_discount CHECK (Discount_percent > 0 AND Discount_percent <= 100),
	CONSTRAINT chk_promo_dates CHECK (Expires_at > Starts_at)
);

CREATE TABLE IF NOT EXISTS Promotion_Redemption (
	Redemption_ID INT AUTO_INCREMENT PRIMARY KEY,
	Promo_ID INT NOT NULL,
	User_ID INT NOT NULL,
	Payment_ID INT NULL,
	Discount_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
	Redeemed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	INDEX idx_redemption_promo (Promo_ID),
	CONSTRAINT fk_redemption_promo FOREIGN KEY (Promo_ID) REFERENCES Promotion(Promo_ID) ON DELETE CASCADE,
	CONSTRAINT fk_redemption_user FOREIGN KEY (User_ID) REFERENCES User(User_ID) ON DELETE CASCADE,
	CONSTRAINT fk_redemption_payment FOREIGN KEY (Payment_ID) REFERENCES Payment(Payment_ID) ON DELETE SET NULL
);

-- Demo promotions spread across the three board columns.
INSERT IGNORE INTO Promotion (Code, Title, Description, Discount_percent, Max_redemptions, Redemption_count, Min_spend, Starts_at, Expires_at) VALUES
('WELCOME15', 'Welcome offer', '15% off your first order', 15, 500, 128, 0, NOW() - INTERVAL 10 DAY, NOW() + INTERVAL 50 DAY),
('LATTE20', 'Latte Tuesday', 'Every Tuesday, 20% off lattes', 20, 200, 187, 15, NOW() - INTERVAL 3 DAY, NOW() + INTERVAL 11 DAY),
('BREW10', 'Weekend brew', '10% off orders above RM30', 10, NULL, 42, 30, NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 6 DAY),
('XMAS25', 'Christmas special', '25% storewide over the holidays', 25, 1000, 0, 20, NOW() + INTERVAL 30 DAY, NOW() + INTERVAL 45 DAY),
('NEWYEAR30', 'New Year rush', 'Ring in the year with 30% off', 30, 300, 0, 25, NOW() + INTERVAL 50 DAY, NOW() + INTERVAL 55 DAY),
('MERDEKA31', 'Merdeka deal', '31% off on Merdeka', 31, 310, 310, 0, NOW() - INTERVAL 80 DAY, NOW() - INTERVAL 60 DAY),
('RAYA20', 'Raya treats', '20% off sweet treats', 20, 400, 233, 10, NOW() - INTERVAL 200 DAY, NOW() - INTERVAL 170 DAY);
