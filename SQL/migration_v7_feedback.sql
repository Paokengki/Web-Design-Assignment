-- ============================================================================
-- CafeDash v7 migration — Feedback & Ratings module
-- Run AFTER migration_v2 to v6. Additive only; safe to re-run (MariaDB 10.4 / XAMPP).
--
--   C:\xampp\mysql\bin\mysql.exe -uroot cafedash_db < SQL/migration_v7_feedback.sql
--   Or phpMyAdmin: cafedash_db -> Import
--
-- HOW IT WORKS
--   A customer rates a DELIVERED order (Order_Tracking.Order_status = 'Delivered') 1-5 stars with a
--   written review. One review per order. They may edit it within 24 hours.
--   Staff reply to it, move Issue_status Pending -> Investigating -> Resolved, or HIDE it
--   (soft delete: Is_hidden = 1, the row is never removed, and it can be restored).
--   Restaurant_ID is NULL when one order spans several cafés.
-- ============================================================================

USE cafedash_db;

CREATE TABLE IF NOT EXISTS Review (
	Review_ID INT AUTO_INCREMENT PRIMARY KEY,
	Payment_ID INT NOT NULL UNIQUE,          -- one review per order
	User_ID INT NOT NULL,
	Restaurant_ID INT NULL,
	Rating TINYINT NOT NULL,
	Comment VARCHAR(1000) NOT NULL,
	Issue_status ENUM('Pending', 'Investigating', 'Resolved') NOT NULL DEFAULT 'Pending',
	Admin_reply VARCHAR(1000) NULL,
	Replied_by INT NULL,                     -- Staff_ID
	Replied_at DATETIME NULL,
	Is_hidden TINYINT(1) NOT NULL DEFAULT 0, -- soft delete / moderation
	Hidden_by INT NULL,                      -- Staff_ID
	Hidden_at DATETIME NULL,
	Edited_at DATETIME NULL,
	Created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	Updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	CONSTRAINT chk_review_rating CHECK (Rating BETWEEN 1 AND 5),
	CONSTRAINT fk_review_payment FOREIGN KEY (Payment_ID) REFERENCES Payment(Payment_ID) ON DELETE RESTRICT,
	CONSTRAINT fk_review_user FOREIGN KEY (User_ID) REFERENCES User(User_ID) ON DELETE RESTRICT,
	CONSTRAINT fk_review_restaurant FOREIGN KEY (Restaurant_ID) REFERENCES Restaurant(Restaurant_ID) ON DELETE SET NULL,
	CONSTRAINT fk_review_replied_by FOREIGN KEY (Replied_by) REFERENCES Staff(Staff_ID) ON DELETE SET NULL,
	CONSTRAINT fk_review_hidden_by FOREIGN KEY (Hidden_by) REFERENCES Staff(Staff_ID) ON DELETE SET NULL,
	INDEX idx_review_user (User_ID, Created_at),
	INDEX idx_review_restaurant (Restaurant_ID, Is_hidden),
	INDEX idx_review_status (Issue_status),
	INDEX idx_review_created (Created_at)
) ENGINE=InnoDB;
