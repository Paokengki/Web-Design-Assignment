CREATE DATABASE IF NOT EXISTS cafedash_db;
USE cafedash_db;

-- 1. User Table
CREATE TABLE User (
    User_ID INT AUTO_INCREMENT PRIMARY KEY,
    User_Name VARCHAR(255) NOT NULL UNIQUE,
    Password VARCHAR(255) NOT NULL,
    Address TEXT,
    Contain_number VARCHAR(20),
    Email VARCHAR(255) UNIQUE,
    Suspend BOOLEAN DEFAULT FALSE
);

-- 2. Driver Table
CREATE TABLE Driver (
    Driver_ID INT AUTO_INCREMENT PRIMARY KEY,
    Driver_Name VARCHAR(255) NOT NULL,
    Plate_number VARCHAR(50)
);

-- 3. Admin Table
CREATE TABLE Admin (
    Admin_ID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(255) NOT NULL,
    Password VARCHAR(255) NOT NULL,
    Contain_number VARCHAR(20),
    Email VARCHAR(255) UNIQUE 
);

-- 4. Restaurant Table
CREATE TABLE Restaurant (
    Restaurant_ID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(255) NOT NULL,
    Address TEXT,
    Restaurant_type VARCHAR(100),
    Rating DECIMAL(3, 2),
    Contain_number VARCHAR(20),
    Email VARCHAR(255) 
);

-- 5. Payment Table
-- Stores one finalized transaction record (header-level data).
CREATE TABLE Payment (
    Payment_ID INT AUTO_INCREMENT PRIMARY KEY,
    User_ID INT NOT NULL,
    Restaurant_ID INT NULL,
    Payment_type VARCHAR(50) DEFAULT 'card',
    Payment_amount DECIMAL(10, 2) NOT NULL,
    Subtotal_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    SST_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    Currency CHAR(3) NOT NULL DEFAULT 'MYR',
    Payment_status ENUM('PENDING', 'SUCCEEDED', 'FAILED', 'REFUNDED') NOT NULL DEFAULT 'PENDING',
    Provider VARCHAR(30) NOT NULL DEFAULT 'STRIPE',
    Provider_payment_id VARCHAR(100) NULL UNIQUE,
    Created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    Paid_at DATETIME NULL,
    FOREIGN KEY (User_ID) REFERENCES User(User_ID),
    FOREIGN KEY (Restaurant_ID) REFERENCES Restaurant(Restaurant_ID)
);

CREATE INDEX idx_payment_user_created ON Payment(User_ID, Created_at);
CREATE INDEX idx_payment_restaurant_status ON Payment(Restaurant_ID, Payment_status);

-- 6. Food Table
CREATE TABLE Food (
    Food_ID INT AUTO_INCREMENT PRIMARY KEY,
    Restaurant_ID INT,
    Name VARCHAR(255) NOT NULL,
    Food_type VARCHAR(100),
    detail TEXT,
    FOREIGN KEY (Restaurant_ID) REFERENCES Restaurant(Restaurant_ID)
);
ALTER TABLE Food
ADD COLUMN amount DECIMAL(10,2);

-- 6b. Payment Item Table (for Bills details)
-- Stores purchased line items as snapshots for reliable Bills history.
CREATE TABLE Payment_Item (
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
    FOREIGN KEY (Food_ID) REFERENCES Food(Food_ID) ON DELETE SET NULL
);

CREATE INDEX idx_payment_item_payment ON Payment_Item(Payment_ID);

-- 7. contact us Table
CREATE TABLE contact_us (
    id INT(11) NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);

-- 8. password resets Table
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);