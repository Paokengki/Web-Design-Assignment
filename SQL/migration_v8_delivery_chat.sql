-- ============================================================================
-- CafeDash v8 migration — driver pools, demo order lifecycle, cancel & refund,
-- points on order completion, and private chat.
-- Run AFTER v2, v3, v4 (tracking) and v5 (rewards); does not depend on v6 (applications) or v7 (feedback).
-- Additive only; safe to re-run.
--
--   C:\xampp\mysql\bin\mysql.exe -uroot cafedash_db < SQL/migration_v8_delivery_chat.sql
-- ============================================================================

USE cafedash_db;

-- ---------------------------------------------------------------------------
-- 1. POINTS ARE EARNED WHEN THE CUSTOMER COMPLETES THE ORDER
--    Points_rule = 'legacy'       -> paid before this change: points count as soon as paid (as before)
--    Points_rule = 'on_complete'  -> new orders: points count only once Completed_at is set
--    The column is added with default 'legacy' (so every EXISTING order keeps its points),
--    then the default switches to 'on_complete' for every order placed from now on.
-- ---------------------------------------------------------------------------
ALTER TABLE Payment
	ADD COLUMN IF NOT EXISTS Points_rule ENUM('legacy', 'on_complete') NOT NULL DEFAULT 'legacy' AFTER Promo_ID,
	ADD COLUMN IF NOT EXISTS Completed_at DATETIME NULL DEFAULT NULL AFTER Paid_at,
	ADD COLUMN IF NOT EXISTS Refund_id VARCHAR(100) NULL DEFAULT NULL AFTER Completed_at,
	ADD COLUMN IF NOT EXISTS Refund_amount DECIMAL(10, 2) NULL DEFAULT NULL AFTER Refund_id,
	ADD COLUMN IF NOT EXISTS Refunded_at DATETIME NULL DEFAULT NULL AFTER Refund_amount;
ALTER TABLE Payment ALTER COLUMN Points_rule SET DEFAULT 'on_complete';

-- ---------------------------------------------------------------------------
-- 2. DEMO LIFECYCLE: when the order entered its current status (drives the timer)
-- ---------------------------------------------------------------------------
ALTER TABLE Order_Tracking
	ADD COLUMN IF NOT EXISTS Status_changed_at DATETIME NULL DEFAULT NULL AFTER Order_status;
UPDATE Order_Tracking SET Status_changed_at = Updated_at WHERE Status_changed_at IS NULL;

-- ---------------------------------------------------------------------------
-- 3. PRIVATE CHAT — one conversation per order per party (restaurant / driver / admin)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS Chat_Thread (
	Thread_ID INT AUTO_INCREMENT PRIMARY KEY,
	Payment_ID INT NOT NULL,
	User_ID INT NOT NULL,
	Party ENUM('restaurant', 'driver', 'admin') NOT NULL,
	Restaurant_ID INT NULL,           -- restaurant chats: the café answering
	Driver_ID INT NULL,               -- driver chats: the courier delivering
	Customer_read_id INT NOT NULL DEFAULT 0,  -- last message id the customer has seen
	Staff_read_id INT NOT NULL DEFAULT 0,     -- last message id the staff side has seen
	Last_message_at DATETIME NULL,
	Created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE KEY uk_thread (Payment_ID, Party),
	INDEX idx_thread_user (User_ID),
	INDEX idx_thread_restaurant (Restaurant_ID),
	INDEX idx_thread_driver (Driver_ID),
	CONSTRAINT fk_thread_payment FOREIGN KEY (Payment_ID) REFERENCES Payment(Payment_ID) ON DELETE CASCADE,
	CONSTRAINT fk_thread_user FOREIGN KEY (User_ID) REFERENCES User(User_ID) ON DELETE CASCADE,
	CONSTRAINT fk_thread_restaurant FOREIGN KEY (Restaurant_ID) REFERENCES Restaurant(Restaurant_ID) ON DELETE SET NULL,
	CONSTRAINT fk_thread_driver FOREIGN KEY (Driver_ID) REFERENCES Staff(Staff_ID) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Chat_Message (
	Message_ID INT AUTO_INCREMENT PRIMARY KEY,
	Thread_ID INT NOT NULL,
	Sender ENUM('customer', 'restaurant', 'driver', 'admin') NOT NULL,
	Staff_ID INT NULL,                -- which staff member replied (NULL for customer / automatic)
	Body VARCHAR(1000) NOT NULL,
	Is_auto TINYINT(1) NOT NULL DEFAULT 0,
	Created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	INDEX idx_msg_thread (Thread_ID, Message_ID),
	CONSTRAINT fk_msg_thread FOREIGN KEY (Thread_ID) REFERENCES Chat_Thread(Thread_ID) ON DELETE CASCADE,
	CONSTRAINT fk_msg_staff FOREIGN KEY (Staff_ID) REFERENCES Staff(Staff_ID) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- 4. DRIVER POOLS — 10 couriers per café (Staff.Assigned_Restaurant_ID = their café).
--    A driver is "busy" while assigned to an order that is not Delivered/Cancelled;
--    that is worked out from Order_Tracking, so there is no separate flag to drift.
--    Sign in at auth/staff_login.php — email driverNN.cafeX@cafedash.com, password Driver@1234
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO Staff (Name, Email, Phone, Password, Role, Plate_number, Assigned_Restaurant_ID) VALUES
('Kavitha Chong', 'driver01.cafe1@cafedash.com', '019-724 2681', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'WTC 9947', 1),
('Farah Chan', 'driver02.cafe1@cafedash.com', '017-786 9975', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BNE 9709', 1),
('Irfan Abdullah', 'driver03.cafe1@cafedash.com', '011-828 2322', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BMK 4806', 1),
('Vikram Wong', 'driver04.cafe1@cafedash.com', '018-211 9032', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VDN 3544', 1),
('Ken Kumar', 'driver05.cafe1@cafedash.com', '016-565 7166', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VFG 1350', 1),
('Liyana Chong', 'driver06.cafe1@cafedash.com', '012-770 9815', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BPQ 4812', 1),
('Irfan Ng', 'driver07.cafe1@cafedash.com', '015-225 7043', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VDN 7654', 1),
('Deepa Lee', 'driver08.cafe1@cafedash.com', '017-799 9166', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BMK 7070', 1),
('Ravi Pillai', 'driver09.cafe1@cafedash.com', '018-607 9595', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BPQ 6980', 1),
('Daniel Nair', 'driver10.cafe1@cafedash.com', '019-730 1415', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'WTC 2213', 1),
('Vikram Lim', 'driver01.cafe2@cafedash.com', '019-886 2680', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'WQA 7801', 2),
('Hafiz Nair', 'driver02.cafe2@cafedash.com', '014-324 8484', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BNE 9039', 2),
('Jun Hao Chan', 'driver03.cafe2@cafedash.com', '017-885 6040', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VFG 8323', 2),
('Suresh Nair', 'driver04.cafe2@cafedash.com', '017-643 4318', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BPQ 4507', 2),
('Ahmad Lim', 'driver05.cafe2@cafedash.com', '013-871 8507', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VFG 8120', 2),
('Aisyah Ismail', 'driver06.cafe2@cafedash.com', '015-909 8955', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BNE 8476', 2),
('Marcus Nair', 'driver07.cafe2@cafedash.com', '017-771 1550', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BPQ 5288', 2),
('Irfan Wong', 'driver08.cafe2@cafedash.com', '018-905 9440', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BPQ 7520', 2),
('Sharmila Rahman', 'driver09.cafe2@cafedash.com', '017-945 8850', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BMK 4465', 2),
('Haziq Nair', 'driver10.cafe2@cafedash.com', '016-298 4188', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'WQA 2497', 2),
('Irfan Tan', 'driver01.cafe3@cafedash.com', '012-200 8392', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VFG 1460', 3),
('Farah Ismail', 'driver02.cafe3@cafedash.com', '014-323 1731', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VFG 4499', 3),
('Yee Ling Yusof', 'driver03.cafe3@cafedash.com', '013-205 6135', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BNE 2309', 3),
('Zara Abdullah', 'driver04.cafe3@cafedash.com', '018-802 3149', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VFG 4862', 3),
('Wei Jie Goh', 'driver05.cafe3@cafedash.com', '013-338 6013', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VFG 6850', 3),
('Ken Ng', 'driver06.cafe3@cafedash.com', '019-401 7160', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'WXY 1838', 3),
('Ken Lee', 'driver07.cafe3@cafedash.com', '011-555 6790', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'WXY 519', 3),
('Ravi Nair', 'driver08.cafe3@cafedash.com', '016-899 5975', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BNE 5441', 3),
('Sharmila Wong', 'driver09.cafe3@cafedash.com', '018-677 3797', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VBA 9787', 3),
('Ravi Ismail', 'driver10.cafe3@cafedash.com', '017-838 6511', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BNE 9901', 3),
('Nurul Lee', 'driver01.cafe4@cafedash.com', '019-608 1559', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'WTC 2940', 4),
('Siti Chan', 'driver02.cafe4@cafedash.com', '011-662 4088', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BNE 435', 4),
('Hafiz Teo', 'driver03.cafe4@cafedash.com', '014-729 3256', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VBA 3147', 4),
('Deepa Chan', 'driver04.cafe4@cafedash.com', '017-280 7278', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BPQ 3167', 4),
('Aisyah Rahman', 'driver05.cafe4@cafedash.com', '015-675 9152', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'WQA 4813', 4),
('Irfan Rahman', 'driver06.cafe4@cafedash.com', '016-731 8897', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BNE 2067', 4),
('Ravi Teo', 'driver07.cafe4@cafedash.com', '015-296 4511', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'WTC 180', 4),
('Wei Jie Hassan', 'driver08.cafe4@cafedash.com', '016-617 9930', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VBA 545', 4),
('Aisyah Chan', 'driver09.cafe4@cafedash.com', '019-421 2797', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BPQ 9206', 4),
('Marcus Zainal', 'driver10.cafe4@cafedash.com', '014-783 5828', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BPQ 9205', 4),
('Hafiz Wong', 'driver01.cafe5@cafedash.com', '015-298 8144', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VBA 1837', 5),
('Yee Ling Lim', 'driver02.cafe5@cafedash.com', '016-939 4039', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BMK 8400', 5),
('Faiz Kumar', 'driver03.cafe5@cafedash.com', '012-498 6154', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VBA 4373', 5),
('Jun Hao Lee', 'driver04.cafe5@cafedash.com', '011-621 5211', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'WQA 352', 5),
('Siti Wong', 'driver05.cafe5@cafedash.com', '014-223 6077', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BMK 1637', 5),
('Yee Ling Chan', 'driver06.cafe5@cafedash.com', '016-522 8471', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'WXY 6722', 5),
('Faiz Pillai', 'driver07.cafe5@cafedash.com', '014-808 8580', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'WQA 2056', 5),
('Chloe Teo', 'driver08.cafe5@cafedash.com', '015-598 5782', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VDN 1044', 5),
('Liyana Goh', 'driver09.cafe5@cafedash.com', '011-488 6415', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BPQ 2104', 5),
('Priya Goh', 'driver10.cafe5@cafedash.com', '016-799 1332', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VFG 5711', 5),
('Arjun Chan', 'driver01.cafe6@cafedash.com', '011-938 2844', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VBA 2110', 6),
('Kavitha Lim', 'driver02.cafe6@cafedash.com', '013-735 6800', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BPQ 571', 6),
('Deepa Zainal', 'driver03.cafe6@cafedash.com', '013-564 7804', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'WQA 6279', 6),
('Nurul Goh', 'driver04.cafe6@cafedash.com', '014-993 3051', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VBA 8995', 6),
('Ahmad Chan', 'driver05.cafe6@cafedash.com', '019-606 5177', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VBA 3839', 6),
('Ahmad Tan', 'driver06.cafe6@cafedash.com', '015-754 8845', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'WQA 4422', 6),
('Faiz Ng', 'driver07.cafe6@cafedash.com', '012-383 1622', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VFG 4445', 6),
('Faiz Teo', 'driver08.cafe6@cafedash.com', '016-257 1186', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'WTC 7809', 6),
('Siti Abdullah', 'driver09.cafe6@cafedash.com', '019-354 7538', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VDN 3909', 6),
('Zara Kumar', 'driver10.cafe6@cafedash.com', '016-405 7139', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VDN 7624', 6),
('Amir Hassan', 'driver01.cafe7@cafedash.com', '019-685 6114', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'WTC 2693', 7),
('Amir Goh', 'driver02.cafe7@cafedash.com', '013-696 1087', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VDN 8672', 7),
('Suresh Goh', 'driver03.cafe7@cafedash.com', '011-854 3827', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BMK 4999', 7),
('Liyana Kumar', 'driver04.cafe7@cafedash.com', '013-931 2650', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'WXY 8839', 7),
('Arjun Zainal', 'driver05.cafe7@cafedash.com', '015-786 1731', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'WQA 6335', 7),
('Zara Teo', 'driver06.cafe7@cafedash.com', '013-720 2719', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BMK 2424', 7),
('Liyana Ong', 'driver07.cafe7@cafedash.com', '016-760 5283', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BPQ 781', 7),
('Liyana Zainal', 'driver08.cafe7@cafedash.com', '014-366 5799', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BPQ 8966', 7),
('Priya Yusof', 'driver09.cafe7@cafedash.com', '013-597 4091', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'WXY 7964', 7),
('Deepa Yusof', 'driver10.cafe7@cafedash.com', '019-374 9457', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'WXY 3042', 7),
('Faiz Chan', 'driver01.cafe8@cafedash.com', '013-367 1465', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BMK 8537', 8),
('Yee Ling Tan', 'driver02.cafe8@cafedash.com', '016-265 7029', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BPQ 3642', 8),
('Zara Ng', 'driver03.cafe8@cafedash.com', '014-329 2741', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VDN 8039', 8),
('Kavitha Lee', 'driver04.cafe8@cafedash.com', '017-468 5939', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BMK 5798', 8),
('Hui Min Nair', 'driver05.cafe8@cafedash.com', '012-768 9502', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'WTC 5976', 8),
('Yee Ling Hassan', 'driver06.cafe8@cafedash.com', '016-284 2131', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BMK 8207', 8),
('Marcus Raj', 'driver07.cafe8@cafedash.com', '015-971 4703', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VBA 5424', 8),
('Deepa Teo', 'driver08.cafe8@cafedash.com', '019-539 6287', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BNE 6703', 8),
('Faiz Rahman', 'driver09.cafe8@cafedash.com', '014-674 4607', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VDN 5836', 8),
('Ken Ismail', 'driver10.cafe8@cafedash.com', '013-652 3090', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'WQA 4484', 8),
('Faiz Wong', 'driver01.cafe9@cafedash.com', '016-472 8281', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BPQ 2481', 9),
('Nurul Chong', 'driver02.cafe9@cafedash.com', '013-774 3019', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BPQ 4908', 9),
('Daniel Hassan', 'driver03.cafe9@cafedash.com', '017-609 1355', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VFG 5493', 9),
('Yee Ling Goh', 'driver04.cafe9@cafedash.com', '016-297 9104', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VFG 8565', 9),
('Arjun Goh', 'driver05.cafe9@cafedash.com', '016-863 6414', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'WXY 6649', 9),
('Liyana Rahman', 'driver06.cafe9@cafedash.com', '011-473 5131', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VBA 5572', 9),
('Daniel Pillai', 'driver07.cafe9@cafedash.com', '017-996 8797', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BNE 5650', 9),
('Marcus Abdullah', 'driver08.cafe9@cafedash.com', '012-876 5401', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VDN 391', 9),
('Marcus Ismail', 'driver09.cafe9@cafedash.com', '018-727 5536', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'VDN 1394', 9),
('Zara Ismail', 'driver10.cafe9@cafedash.com', '019-987 1186', '$2y$10$6yctH7uXIocOaHTNkeO3AeZVTJ8ebyRpLs74sp2PzT6Kjyi//tja6', 'Courier', 'BMK 2653', 9);

-- 5. One restaurant (Kitchen Staff) account per café, so every café can answer its chats.
--    Email kitchen.cafeX@cafedash.com, password Staff@1234
INSERT IGNORE INTO Staff (Name, Email, Phone, Password, Role, Plate_number, Assigned_Restaurant_ID) VALUES
('95 Degres Art Cafe Kitchen', 'kitchen.cafe1@cafedash.com', NULL, '$2y$10$zd3ECCs7jugOg7J3uLpvPexWiKtyJndsAde9Elrp8lrTmZ.qmBFj.', 'Kitchen Staff', NULL, 1),
('Copper Pot Cafe Kitchen', 'kitchen.cafe2@cafedash.com', NULL, '$2y$10$zd3ECCs7jugOg7J3uLpvPexWiKtyJndsAde9Elrp8lrTmZ.qmBFj.', 'Kitchen Staff', NULL, 2),
('Feeka Coffee Roasters Kitchen', 'kitchen.cafe3@cafedash.com', NULL, '$2y$10$zd3ECCs7jugOg7J3uLpvPexWiKtyJndsAde9Elrp8lrTmZ.qmBFj.', 'Kitchen Staff', NULL, 3),
('Good Friends Restaurant & Cafe Kitchen', 'kitchen.cafe4@cafedash.com', NULL, '$2y$10$zd3ECCs7jugOg7J3uLpvPexWiKtyJndsAde9Elrp8lrTmZ.qmBFj.', 'Kitchen Staff', NULL, 4),
('JDV Cafe Kitchen', 'kitchen.cafe5@cafedash.com', NULL, '$2y$10$zd3ECCs7jugOg7J3uLpvPexWiKtyJndsAde9Elrp8lrTmZ.qmBFj.', 'Kitchen Staff', NULL, 5),
('Lil'' Bird & The Big Bear Cafe Kitchen', 'kitchen.cafe6@cafedash.com', NULL, '$2y$10$zd3ECCs7jugOg7J3uLpvPexWiKtyJndsAde9Elrp8lrTmZ.qmBFj.', 'Kitchen Staff', NULL, 6),
('The Foxhole Bakery Cafe Kitchen', 'kitchen.cafe7@cafedash.com', NULL, '$2y$10$zd3ECCs7jugOg7J3uLpvPexWiKtyJndsAde9Elrp8lrTmZ.qmBFj.', 'Kitchen Staff', NULL, 7),
('The Great Cafe Kitchen', 'kitchen.cafe8@cafedash.com', NULL, '$2y$10$zd3ECCs7jugOg7J3uLpvPexWiKtyJndsAde9Elrp8lrTmZ.qmBFj.', 'Kitchen Staff', NULL, 8),
('Upstairs Cafe Kitchen', 'kitchen.cafe9@cafedash.com', NULL, '$2y$10$zd3ECCs7jugOg7J3uLpvPexWiKtyJndsAde9Elrp8lrTmZ.qmBFj.', 'Kitchen Staff', NULL, 9);
