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
(1,1,'95 Cafe bandung Latte','Beverage','A unique floral twist on the classic latte. This drink features a double shot of 100% Arabica espresso blended with silky steamed milk and infused with the sweet, nostalgic aroma of rose syrup (Bandung).',8.50),
(2,1,'95 Face watermelon Lychee Waffle','Dessert','A delicious waffle topped with fresh watermelon and lychee slices.',13.00),
(3,1,'Black truffle Alfredo','Pasta','Creamy pasta tossed in a rich black truffle sauce.',15.00),
(4,1,'Burgundian','Dessert','A decadent chocolate cake with a hint of red wine.',12.50),
(5,1,'Burnt Cheese Cake','Dessert','A rich and creamy cheese cake with a caramelized top.',11.00),
(6,1,'Creamy Lemonade','Dessert','A refreshing blend of lemon juice and creamy texture.',10.50),
(7,1,'Croffle with Toasted coconut crumbles & Gula Melaka','Dessert','A delightful combination of crispy croffle with toasted coconut crumbles and sweet gula melaka.',13.50),
(8,1,'Minimalist Pasta','Pasta','A simple yet delicious pasta dish with a focus on fresh ingredients.',12.00),
(9,1,'Mix Berries waffle','Dessert','A fruity waffle topped with a mix of fresh berries.',13.00),
(10,1,'ethiopia sidama & Guatemala Finca','Beverage','A unique blend of Ethiopian Sidama and Guatemalan Finca coffee beans.',9.50),
(11,2,'Americano','Beverage','A strong and bold coffee drink made with espresso and hot water.',6.50),
(12,2,'Ayam Balado Hijau','Rice','A spicy Indonesian chicken dish served with rice.',11.50),
(13,2,'Buttermilk Chicken Burger','Rice','A juicy chicken burger made with buttermilk and served with rice.',13.50),
(14,2,'Buttermilk Chicken Rice','Rice','Grilled buttermilk chicken served over a bed of rice.',11.00),
(15,2,'Caffe Latte','Beverage','A smooth and creamy coffee drink made with espresso and steamed milk.',8.00),
(16,2,'Caramel Latte','Beverage','A sweet and indulgent latte topped with caramel drizzle.',9.00),
(17,2,'Grilled Cajun Chicken','Western','Succulent chicken breast rubbed with authentic Cajun spices and grilled over high heat. Served with a side of crispy golden fries and a refreshing garden salad.',14.50),
(18,2,'Long Black','Beverage','A classic black coffee made by pouring hot water over a double shot of espresso, resulting in a strong, bold flavor',6.50),
(19,2,'Peppermint','Beverage','A refreshing mint-flavored beverage.',7.00),
(20,2,'Spaghetti Aglio Olio','Pasta','A simple yet delicious pasta dish with a focus on fresh ingredients.',12.50),
(21,2,'Summer Salad','Others','A fresh and healthy salad perfect for the summer months.',10.00),
(22,3,'Ayam Perchik Pasta','Pasta','A delicious pasta dish with a spicy Indonesian chicken twist.',13.50),
(23,3,'Cottage Pie','Pie','A hearty and comforting pie filled with seasoned meat and vegetables.',14.00),
(24,3,'Egg Florentine','Dessert','A classic French dish made with poached eggs and spinach.',11.50),
(25,3,'Half Roasted Spring Chicken','Chicken','A tender and flavorful chicken dish, half-roasted to perfection.',15.00),
(26,3,'Lamb Shank Briyani','Rice','Aromatic rice dish with tender lamb shank and a blend of spices.',15.00),
(27,3,'Mediterranean Roasted Aubergine','Others','Roasted eggplant seasoned with Mediterranean herbs and olive oil.',12.00),
(28,3,'Orange Iced Cafe or Matcha Latte','Beverage','A refreshing blend of orange and iced coffee or matcha latte.',9.50),
(29,3,'Pan Mee','Noodles','A savory noodle soup with a rich broth and fresh vegetables.',10.50),
(30,3,'Roasted Chicken Rice','Rice','Grilled chicken served over a bed of fluffy rice.',10.00),
(31,3,'Steak Frites','Western','A juicy steak served with crispy French fries.',62.00),
(32,3,'Tamar Iced Latte','Beverage','A unique iced latte with a hint of tamarind for a tangy twist.',8.50),
(33,3,'Wagyu Beef Roast','Beef','Premium wagyu beef roasted to perfection and served with seasonal vegetables.',15.00),
(34,3,'Yee Sang','Others','A traditional Malaysian dish made with layered ingredients.',14.00),
(35,4,'Beef Burger','Snack','A hearty burger with a juicy beef patty and fresh toppings.',12.50),
(36,4,'Chicken Ham and Cheese Toastie','Dessert','A delicious toastie filled with grilled chicken, ham, and melted cheese.',11.50),
(37,4,'Eggs Atlantic','Others','Fresh eggs served with a side of toast and coffee.',13.00),
(38,4,'Fish And Chips','Western','Crispy fried fish served with golden fries and tartar sauce.',14.50),
(39,4,'Good Pisang Goreng','Dessert','Crispy fried bananas served with a drizzle of honey.',9.50),
(40,4,'Mozza Sticks','Snack','Golden fried mozzarella sticks served with marinara sauce.',10.50),
(42,4,'Strawberry Waffle','Dessert','A fluffy waffle topped with fresh strawberries and whipped cream.',12.50),
(43,4,'Tom Yum and Salmon Spaghetti','Pasta','A flavorful Thai-inspired pasta dish with salmon and aromatic spices.',14.50),
(44,4,'Trio Mushroom Soup','Snack','A rich and creamy soup featuring three varieties of mushrooms.',10.00),
(45,4,'Tuna And Salad','Others','Fresh tuna served with a side of mixed greens.',11.00),
(46,5,'Butterfly Pea Latte','Beverage','A beautiful blue latte made with butterfly pea flower extract.',8.50),
(47,5,'Cafe con Leche','Beverage','A traditional Spanish coffee drink made with steamed milk.',10.00),
(48,5,'CocoaNut','Beverage','A rich and creamy coconut cocoa drink.',9.00),
(49,5,'Egg Benedict Royale','Others','A luxurious twist on the classic egg benedict.',14.00),
(50,5,'French Onion Creamy Pasta','Pasta','A comforting pasta dish with a rich French onion sauce.',13.50),
(51,5,'French Toast','Dessert','Soft and buttery French toast served with syrup.',11.50),
(52,5,'Italian Meatball','Others','Juicy Italian meatballs served with marinara sauce.',13.00),
(53,5,'Melaka Nyonya Nasi Lemak','Rice','A flavorful rice dish with a coconut milk base and various toppings.',10.50),
(54,5,'Omelette Souffle','Dessert','A light and airy omelette with a soufflé-like texture.',10.00),
(55,5,'Peanut Butter Latte','Beverage','A creamy latte made with peanut butter.',9.00),
(56,5,'Tiramisu Latte','Beverage','A delicious latte infused with tiramisu flavors.',9.50),
(57,5,'Valrhona Dark Cocoa','Dessert','A rich and decadent dark chocolate dessert.',10.00),
(58,6,'Creamy Tuscan Baby Spinach Salmon','Western','A creamy and flavorful salmon dish with baby spinach.',14.50),
(59,6,'Michael Corleone''s Rustica Herbico Beefy Bolo','Pasta','A hearty pasta dish with a beefy sauce and fresh herbs.',13.50),
(60,6,'Pomodoro','Pasta','A simple yet delicious tomato-based pasta dish.',11.50),
(61,6,'Pumpkin Spice Latte','Beverage','A seasonal latte infused with pumpkin spice and vanilla.',9.50),
(62,6,'Spaghetti Alle Vongole','Pasta','A classic Italian pasta dish with clams in a white wine sauce.',14.00),
(63,6,'The Caribbean Coconut Pancake','Dessert','A fluffy pancake infused with coconut and tropical fruits.',12.50),
(64,6,'The Classic French Croissant Board','Western','A selection of freshly baked French croissants served with butter and jam.',13.00),
(65,6,'The Nutty Banana Smoothie','Beverage','A refreshing smoothie made with ripe bananas and nuts.',9.00),
(66,6,'The Royal Danish Breakfast','Western','A hearty breakfast platter featuring Danish pastries and fresh ingredients.',14.50),
(67,6,'The Village Crispy Chicken Burger','Western','A delicious burger with a crispy chicken patty and fresh vegetables.',12.50),
(68,6,'Wild Basil Seafood Pesto Pasta','Pasta','A flavorful pasta dish with a pesto sauce and assorted seafood.',14.50),
(69,7,'Avocado Toast','Western','A healthy toast topped with ripe avocado and seasonings.',12.50),
(70,7,'Best of Breakfast Sharing Platter','Western','A delightful assortment of breakfast items for sharing.',40.00),
(71,7,'Burnt Cheesecake','Dessert','A rich and creamy cheesecake with a caramelized top.',11.50),
(72,7,'Cappuccino','Beverage','A perfect balance of espresso, steamed milk, and foam.',8.00),
(73,7,'Creamy Wild Mushroom Soup','Snack','A velvety soup infused with wild mushrooms and herbs.',10.50),
(74,7,'Flat White','Beverage','A smooth and creamy coffee drink with a strong espresso base.',10.00),
(75,7,'Latte','Beverage','A rich and creamy coffee drink with a strong espresso base.',8.50),
(76,7,'Loaded Omelette','Western','A fluffy omelette filled with your choice of ingredients.',11.00),
(77,7,'Pasta Primavera','Pasta','A seasonal pasta dish with fresh vegetables and a light sauce.',13.50),
(78,7,'Piccolo Latte','Beverage','A small and strong espresso-based coffee drink.',7.50),
(79,7,'Salmon Scramble','Western','A delicious scramble with fresh salmon and vegetables.',14.50),
(80,7,'The Great Green Breakfast (Vegan)','Western','A healthy and hearty breakfast option for vegans.',13.50),
(81,7,'The Works','Western','A classic burger with all the fixings.',14.50),
(82,7,'Tiramisu','Dessert','A traditional Italian dessert with layers of coffee-soaked ladyfingers and mascarpone cream.',11.50),
(83,8,'Deep Ocean','Beverage','A refreshing drink with a tropical twist.',10.50),
(84,8,'Dry Curry Ramen','Noodles','A spicy ramen dish with a dry curry sauce.',12.50),
(85,8,'Fresh Apple Juice','Beverage','A fresh and healthy juice made from real apples.',8.00),
(86,8,'Fried Ramen','Noodles','A crispy and flavorful ramen dish.',11.50),
(87,8,'Korean Kimchi Fried Ramen','Noodles','A spicy and tangy ramen dish with kimchi.',12.50),
(88,8,'Morning Ramen','Noodles','A comforting bowl of ramen perfect for breakfast.',10.50),
(89,8,'Morning Toast','Dessert','A delicious toast topped with fresh fruits and honey.',9.50),
(90,8,'Nyonya Curry Ramen','Noodles','A aromatic ramen dish with a rich and spicy curry flavor.',12.50),
(91,8,'Orange Juice','Beverage','A refreshing juice made from fresh oranges.',7.50),
(92,8,'Phoenix','Beverage','A unique blend of tropical fruits and herbs.',11.00),
(93,8,'Premium Local Milk Tea','Beverage','A high-quality milk tea made with locally sourced ingredients.',8.50),
(94,8,'Spicy Indon Sambal Ramen','Noodles','A fiery ramen dish with a spicy sambal sauce.',12.50),
(95,8,'Vibrant Sun','Beverage','A vibrant and refreshing drink.',10.50),
(96,9,'Carbonara Fettuccine','Pasta','A classic Italian pasta dish with a creamy sauce.',13.50),
(97,9,'Cheesesteak Sandwich','Western','A hearty sandwich with melted cheese and seasoned meat.',9.50),
(98,9,'Creamy Pesto','Pasta','A delicious pasta dish with a creamy pesto sauce.',12.50),
(99,9,'Creme Brulee','Dessert','A traditional French dessert with a caramelized sugar top.',11.50),
(100,9,'Everything Chicken Ramen','Noodles','A flavorful ramen dish with a variety of chicken toppings.',12.50),
(101,9,'Greek Yoghurt Berry Cake','Dessert','A refreshing cake with Greek yoghurt and fresh berries.',12.50);

-- Seed admin
INSERT INTO Admin (Name, Password, Contain_number, Email)
VALUES ('admin', 'admin123', '012-3456789', 'admin@cafedash.com');
