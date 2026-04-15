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
CREATE TABLE Payment (
    Payment_ID INT AUTO_INCREMENT PRIMARY KEY,
    Payment_type VARCHAR(50),
    Payment_amount DECIMAL(10, 2)
);

-- 6. Food Table
CREATE TABLE Food (
    Food_ID INT AUTO_INCREMENT PRIMARY KEY,
    Restaurant_ID INT,
    Name VARCHAR(255) NOT NULL,
    Food_type VARCHAR(100),
    detail TEXT,
    FOREIGN KEY (Restaurant_ID) REFERENCES Restaurant(Restaurant_ID)
);

-- 7. Order Table
CREATE TABLE `Order` (
    Order_ID INT AUTO_INCREMENT PRIMARY KEY,
    User_ID INT,
    Food_ID INT,
    Payment_ID INT,
    Remark TEXT,
    FOREIGN KEY (User_ID) REFERENCES User(User_ID),
    FOREIGN KEY (Food_ID) REFERENCES Food(Food_ID),
    FOREIGN KEY (Payment_ID) REFERENCES Payment(Payment_ID)
);

-- 8. Delivery Table
CREATE TABLE Delivery (
    Delivery_ID INT AUTO_INCREMENT PRIMARY KEY,
    Driver_ID INT,
    Order_ID INT,
    FOREIGN KEY (Driver_ID) REFERENCES Driver(Driver_ID),
    FOREIGN KEY (Order_ID) REFERENCES `Order`(Order_ID)
);

-- 9. History Table
CREATE TABLE History (
    History_ID INT AUTO_INCREMENT PRIMARY KEY,
    User_ID INT,
    Order_ID INT,
    FOREIGN KEY (User_ID) REFERENCES User(User_ID),
    FOREIGN KEY (Order_ID) REFERENCES `Order`(Order_ID)
);