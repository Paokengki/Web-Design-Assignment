-- ============================================================================
-- CafeDash v6 migration — Café registration applications
-- Run AFTER migration_v2 to v5. Additive only; safe to re-run (MariaDB 10.4 / XAMPP).
--
--   C:\xampp\mysql\bin\mysql.exe -uroot cafedash_db < SQL/migration_v6_applications.sql
--   Or phpMyAdmin: cafedash_db -> Import
--
-- HOW IT WORKS
--   A café owner submits the public form (customer/register_cafe.php) -> a Pending row here.
--   Staff approve it in admin/applications.php -> a Restaurant row is created, plus a
--   Kitchen Staff account (Staff.Assigned_Restaurant_ID) for the owner. The Restaurant table
--   itself is NOT changed, so existing pages keep working.
-- ============================================================================

USE cafedash_db;

CREATE TABLE IF NOT EXISTS Restaurant_Application (
	Application_ID INT AUTO_INCREMENT PRIMARY KEY,
	Name VARCHAR(120) NOT NULL,
	Restaurant_type VARCHAR(30) NOT NULL DEFAULT 'Cafe',
	Owner_Name VARCHAR(100) NOT NULL,
	Email VARCHAR(150) NOT NULL,
	Contain_number VARCHAR(20) NOT NULL,
	Address VARCHAR(255) NOT NULL,
	Status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
	Reject_reason VARCHAR(255) NULL,
	Reviewed_By INT NULL,                -- Staff who approved / rejected
	Reviewed_At DATETIME NULL,
	Restaurant_ID INT NULL,              -- the café created on approval
	Created_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	INDEX idx_application_status (Status, Created_At),
	INDEX idx_application_email (Email),
	CONSTRAINT fk_application_staff FOREIGN KEY (Reviewed_By) REFERENCES Staff(Staff_ID) ON DELETE SET NULL,
	CONSTRAINT fk_application_restaurant FOREIGN KEY (Restaurant_ID) REFERENCES Restaurant(Restaurant_ID) ON DELETE SET NULL
) ENGINE=InnoDB;
