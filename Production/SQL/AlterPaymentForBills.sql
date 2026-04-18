USE cafedash_db;

-- Upgrade existing Payment table to support Bills history and provider reconciliation.
ALTER TABLE Payment
    ADD COLUMN User_ID INT NOT NULL AFTER Payment_ID,
    ADD COLUMN Restaurant_ID INT NULL AFTER User_ID,
    ADD COLUMN Subtotal_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER Payment_amount,
    ADD COLUMN SST_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER Subtotal_amount,
    ADD COLUMN Currency CHAR(3) NOT NULL DEFAULT 'MYR' AFTER SST_amount,
    ADD COLUMN Payment_status ENUM('PENDING', 'SUCCEEDED', 'FAILED', 'REFUNDED') NOT NULL DEFAULT 'PENDING' AFTER Currency,
    ADD COLUMN Provider VARCHAR(30) NOT NULL DEFAULT 'STRIPE' AFTER Payment_status,
    ADD COLUMN Provider_payment_id VARCHAR(100) NULL AFTER Provider,
    ADD COLUMN Created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER Provider_payment_id,
    ADD COLUMN Paid_at DATETIME NULL AFTER Created_at;

ALTER TABLE Payment
    MODIFY COLUMN Payment_type VARCHAR(50) DEFAULT 'card',
    MODIFY COLUMN Payment_amount DECIMAL(10, 2) NOT NULL;

ALTER TABLE Payment
    ADD CONSTRAINT fk_payment_user FOREIGN KEY (User_ID) REFERENCES User(User_ID),
    ADD CONSTRAINT fk_payment_restaurant FOREIGN KEY (Restaurant_ID) REFERENCES Restaurant(Restaurant_ID),
    ADD UNIQUE KEY uq_payment_provider_payment_id (Provider_payment_id),
    ADD INDEX idx_payment_user_created (User_ID, Created_at),
    ADD INDEX idx_payment_restaurant_status (Restaurant_ID, Payment_status);

-- Create line-item table for order history display at item level.
CREATE TABLE IF NOT EXISTS Payment_Item (
    Payment_Item_ID INT AUTO_INCREMENT PRIMARY KEY,
    Payment_ID INT NOT NULL,
    Food_ID INT NULL,
    Item_name VARCHAR(255) NOT NULL,
    Item_type VARCHAR(100),
    Unit_amount DECIMAL(10, 2) NOT NULL,
    Quantity INT NOT NULL,
    Line_total DECIMAL(10, 2) NOT NULL,
    Sugar_level VARCHAR(20),
    Ice_level VARCHAR(20),
    Remark TEXT,
    Created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (Payment_ID) REFERENCES Payment(Payment_ID) ON DELETE CASCADE,
    FOREIGN KEY (Food_ID) REFERENCES Food(Food_ID) ON DELETE SET NULL,
    INDEX idx_payment_item_payment (Payment_ID)
);

-- Retire the old order tables after the new Payment / Payment_Item flow is in place.
-- History depends on `Order`, so drop it first.
DROP TABLE IF EXISTS History;
DROP TABLE IF EXISTS `Order`;