CREATE DATABASE IF NOT EXISTS cafedash_db;
USE cafedash_db;

-- Recreate tables safely
DROP TABLE IF EXISTS Payment_Item;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS contact_us;
DROP TABLE IF EXISTS Payment;
DROP TABLE IF EXISTS Food;
DROP TABLE IF EXISTS Driver;
DROP TABLE IF EXISTS Admin;
DROP TABLE IF EXISTS Restaurant;
DROP TABLE IF EXISTS User;

-- 1. User Table
CREATE TABLE User (
	User_ID INT AUTO_INCREMENT PRIMARY KEY,
	User_Name VARCHAR(255) NOT NULL UNIQUE,
	Password VARCHAR(255) NOT NULL,
	Address TEXT,
	Contain_number VARCHAR(20),
	Email VARCHAR(255) UNIQUE,
	Profile_Image VARCHAR(255),
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
	amount DECIMAL(10, 2),
	FOREIGN KEY (Restaurant_ID) REFERENCES Restaurant(Restaurant_ID)
);

-- 7. Payment Item Table
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

-- 8. Contact Us Table
CREATE TABLE contact_us (
	id INT(11) NOT NULL AUTO_INCREMENT,
	name VARCHAR(255) NOT NULL,
	phone VARCHAR(20) NOT NULL,
	email VARCHAR(255) NOT NULL,
	message TEXT NOT NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id)
);

-- 9. Password Resets Table
CREATE TABLE password_resets (
	id INT AUTO_INCREMENT PRIMARY KEY,
	user_id INT NOT NULL,
	token_hash VARCHAR(64) NOT NULL,
	expires_at DATETIME NOT NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed users
INSERT INTO User (User_Name, Password, Address, Contain_number, Email, Suspend)
VALUES
('Linus', '12345', '123 Coffee Lane, KL', '012-3456789', 'linus@gmail.com', FALSE),
('Sarah_Admin', 'admin789', '456 Baker St, PJ', '011-9876543', 'sarah@outlook.com', FALSE),
('Test_User', 'password', '789 Demo Blvd, Subang', '017-1112223', 'test@yahoo.com', FALSE);

-- Seed restaurants
INSERT INTO Restaurant (Restaurant_ID, Name, Address, Restaurant_type, Rating, Contain_number, Email) VALUES
(1, '95 Degres Art Cafe', '1, Jalan SS 15/8a, Ss 15, 47500 Subang Jaya, Selangor', 'Food', 4.80, '03-8600 1777', '95@cafedash.com'),
(2, 'Copper Pot Cafe', '23, Jalan USJ 11/4g, Subang Jaya, 47620 Subang Jaya, Selangor', 'Cafe', 4.70, '03-8600 1777', 'copperpot@cafedash.com'),
(3, 'Feeka Coffee Roasters', '53, Jalan SS 15/5a, Ss 15, 47500 Subang Jaya, Selangor', 'Cafe', 4.90, '03-8600 1777', 'feeka@cafedash.com'),
(4, 'Good Friends Restaurant & Cafe', '10, Jalan USJ 10/1a, Subang Jaya, 47620 Subang Jaya, Selangor', 'Cafe', 4.30, '03-8600 1777', 'goodfriends@cafedash.com'),
(5, 'JDV Cafe', '36, Jalan USJ 9/5p, Subang Jaya, 47620 Subang Jaya, Selangor', 'Cafe', 4.50, '03-8600 1777', 'jdv@cafedash.com'),
(6, 'Lil'' Bird & The Big Bear Cafe', '12, Jalan USJ 4/7g, Subang Jaya, 47600 Subang Jaya, Selangor', 'Food', 4.20, '03-8600 1777', 'lilbird@cafedash.com'),
(7, 'The Foxhole Bakery Cafe', '20, Jalan USJ 1/3c, Subang Jaya, 47600 Subang Jaya, Selangor', 'Cafe', 4.10, '03-8600 1777', 'foxhole@cafedash.com'),
(8, 'The Great Cafe', '42, Jalan USJ 21/10, Subang Jaya, 47630 Subang Jaya, Selangor', 'Food', 4.45, '03-8600 1777', 'great@cafedash.com'),
(9, 'Upstairs Cafe', '14, Jalan USJ 3/2d, Subang Jaya, 47610 Subang Jaya, Selangor', 'Cafe', 4.60, '03-8600 1777', 'upstairs@cafedash.com');

-- Seed foods
INSERT INTO Food (Food_ID, Restaurant_ID, Name, Food_type, detail, amount) VALUES
(1,1,'95 Cafe bandung Latte','Beverage','Imported from material folder',8.50),
(2,1,'95 Face watermelon Lychee Waffle','Dessert','Imported from material folder',13.00),
(3,1,'Black truffle Alfredo','Pasta','Imported from material folder',15.00),
(4,1,'Burgundian','Dessert','Imported from material folder',12.50),
(5,1,'Burnt Cheese Cake','Dessert','Imported from material folder',11.00),
(6,1,'Creamy Lemonade','Dessert','Imported from material folder',10.50),
(7,1,'Croffle with Toasted coconut crumbles & Gula Melaka','Dessert','Imported from material folder',13.50),
(8,1,'Minimalist Pasta','Pasta','Imported from material folder',12.00),
(9,1,'Mix Berries waffle','Dessert','Imported from material folder',13.00),
(10,1,'ethiopia sidama & Guatemala Finca','Beverage','Imported from material folder',9.50),
(11,2,'Americano','Beverage','Imported from material folder',6.50),
(12,2,'Ayam Balado Hijau','Rice','Imported from material folder',11.50),
(13,2,'Buttermilk Chicken Burger','Rice','Imported from material folder',13.50),
(14,2,'Buttermilk Chicken Rice','Rice','Imported from material folder',11.00),
(15,2,'Caffe Latte','Beverage','Imported from material folder',8.00),
(16,2,'Caramel Latte','Beverage','Imported from material folder',9.00),
(17,2,'Grilled Cajun Chicken','Western','Imported from material folder',14.50),
(18,2,'Long Black','Beverage','Imported from material folder',6.50),
(19,2,'Peppermint','Beverage','Imported from material folder',7.00),
(20,2,'Spaghetti Aglio Olio','Pasta','Imported from material folder',12.50),
(21,2,'Summer Salad','Others','Imported from material folder',10.00),
(22,3,'Ayam Perchik Pasta','Pasta','Imported from material folder',13.50),
(23,3,'Cottage Pie','Pie','Imported from material folder',14.00),
(24,3,'Egg Florentine','Dessert','Imported from material folder',11.50),
(25,3,'Half Roasted Spring Chicken','Chicken','Imported from material folder',15.00),
(26,3,'Lamb Shank Briyani','Rice','Imported from material folder',15.00),
(27,3,'Mediterranean Roasted Aubergine','Others','Imported from material folder',12.00),
(28,3,'Orange Iced Cafe or Matcha Latte','Beverage','Imported from material folder',9.50),
(29,3,'Pan Mee','Noodles','Imported from material folder',10.50),
(30,3,'Roasted Chicken Rice','Rice','Imported from material folder',10.00),
(31,3,'Steak Frites','Western','Imported from material folder',15.00),
(32,3,'Tamar Iced Latte','Beverage','Imported from material folder',8.50),
(33,3,'Wagyu Beef Roast','Beef','Imported from material folder',15.00),
(34,3,'Yee Sang','Others','Imported from material folder',14.00),
(35,4,'Beef Burger','Snack','Imported from material folder',12.50),
(36,4,'Chicken Ham and Cheese Toastie','Dessert','Imported from material folder',11.50),
(37,4,'Eggs Atlantic','Others','Imported from material folder',13.00),
(38,4,'Fish And Chips','Western','Imported from material folder',14.50),
(39,4,'Good Pisang Goreng','Dessert','Imported from material folder',9.50),
(40,4,'Mozza Sticks','Snack','Imported from material folder',10.50),
(42,4,'Strawberry Waffle','Dessert','Imported from material folder',12.50),
(43,4,'Tom Yum and Salmon Spaghetti','Pasta','Imported from material folder',14.50),
(44,4,'Trio Mushroom Soup','Snack','Imported from material folder',10.00),
(45,4,'Tuna And Salad','Others','Imported from material folder',11.00),
(46,5,'Butterfly Pea Latte','Beverage','Imported from material folder',8.50),
(47,5,'Cafe con Leche','Others','Imported from material folder',10.00),
(48,5,'CocoaNut','Beverage','Imported from material folder',9.00),
(49,5,'Egg Benedict Royale','Others','Imported from material folder',14.00),
(50,5,'French Onion Creamy Pasta','Pasta','Imported from material folder',13.50),
(51,5,'French Toast','Dessert','Imported from material folder',11.50),
(52,5,'Italian Meatball','Others','Imported from material folder',13.00),
(53,5,'Melaka Nyonya Nasi Lemak','Rice','Imported from material folder',10.50),
(54,5,'Omelette Souffle','Snack','Imported from material folder',10.00),
(55,5,'Peanut Butter Latte','Beverage','Imported from material folder',9.00),
(56,5,'Tiramisu Latte','Beverage','Imported from material folder',9.50),
(57,5,'Valrhona Dark Cocoa','Beverage','Imported from material folder',10.00),
(58,6,'Creamy Tuscan Baby Spinach Salmon','Others','Imported from material folder',14.50),
(59,6,'Michael Corleone''s Rustica Herbico Beefy Bolo','Others','Imported from material folder',13.50),
(60,6,'Pomodoro','Others','Imported from material folder',11.50),
(61,6,'Pumpkin Spice Latte','Beverage','Imported from material folder',9.50),
(62,6,'Spaghetti Alle Vongole','Pasta','Imported from material folder',14.00),
(63,6,'The Caribbean Coconut Pancake','Dessert','Imported from material folder',12.50),
(64,6,'The Classic French Croissant Board','Others','Imported from material folder',13.00),
(65,6,'The Nutty Banana Smoothie','Beverage','Imported from material folder',9.00),
(66,6,'The Royal Danish Breakfast','Others','Imported from material folder',14.50),
(67,6,'The Village Crispy Chicken Burger','Snack','Imported from material folder',12.50),
(68,6,'Wild Basil Seafood Pesto Pasta','Pasta','Imported from material folder',14.50),
(69,7,'Avocado Toast','Dessert','Imported from material folder',12.50),
(70,7,'Best of Breakfast Sharing Platter','Beverage','Imported from material folder',10.00),
(71,7,'Burnt Cheesecake','Dessert','Imported from material folder',11.50),
(72,7,'Cappuccino','Beverage','Imported from material folder',8.00),
(73,7,'Creamy Wild Mushroom Soup','Snack','Imported from material folder',10.50),
(74,7,'Flat White','Others','Imported from material folder',10.00),
(75,7,'Latte','Beverage','Imported from material folder',8.50),
(76,7,'Loaded Omelette','Snack','Imported from material folder',11.00),
(77,7,'Pasta Primavera','Pasta','Imported from material folder',13.50),
(78,7,'Piccolo Latte','Beverage','Imported from material folder',7.50),
(79,7,'Salmon Scramble','Western','Imported from material folder',14.50),
(80,7,'The Great Green Breakfast (Vegan)','Western','Imported from material folder',13.50),
(81,7,'The Works','Western','Imported from material folder',14.50),
(82,7,'Tiramisu','Dessert','Imported from material folder',11.50),
(83,8,'Deep Ocean','Others','Imported from material folder',10.50),
(84,8,'Dry Curry Ramen','Noodles','Imported from material folder',12.50),
(85,8,'Fresh Apple Juice','Beverage','Imported from material folder',8.00),
(86,8,'Fried Ramen','Noodles','Imported from material folder',11.50),
(87,8,'Korean Kimchi Fried Ramen','Noodles','Imported from material folder',12.50),
(88,8,'Morning Ramen','Noodles','Imported from material folder',10.50),
(89,8,'Morning Toast','Dessert','Imported from material folder',9.50),
(90,8,'Nyonya Curry Ramen','Noodles','Imported from material folder',12.50),
(91,8,'Orange Juice','Beverage','Imported from material folder',7.50),
(92,8,'Phoenix','Others','Imported from material folder',11.00),
(93,8,'Premium Local Milk Tea','Beverage','Imported from material folder',8.50),
(94,8,'Spicy Indon Sambal Ramen','Noodles','Imported from material folder',12.50),
(95,8,'Vibrant Sun','Others','Imported from material folder',10.50),
(96,9,'Carbonara Fettuccine','Pasta','Imported from material folder',13.50),
(97,9,'Cheesesteak Sandwich','Beverage','Imported from material folder',9.50),
(98,9,'Creamy Pesto','Others','Imported from material folder',12.50),
(99,9,'Creme Brulee','Dessert','Imported from material folder',11.50),
(100,9,'Everything Chicken Ramen','Noodles','Imported from material folder',12.50),
(101,9,'Greek Yoghurt Berry Cake','Dessert','Imported from material folder',12.50);

-- Seed admin
INSERT INTO Admin (Name, Password, Contain_number, Email)
VALUES ('admin', 'admin123', '012-3456789', 'admin@cafedash.com');
