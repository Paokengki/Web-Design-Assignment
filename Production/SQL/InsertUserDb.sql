USE cafedash_db;

-- Inserting 3 different types of users for testing
INSERT INTO User (User_Name, Password, Address, Contain_number, Email, Suspend) 
VALUES 
('Linus', '12345', '123 Coffee Lane, KL', '012-3456789', 'linus@gmail.com', FALSE),
('Sarah_Admin', 'admin789', '456 Baker St, PJ', '011-9876543', 'sarah@outlook.com', FALSE),
('Test_User', 'password', '789 Demo Blvd, Subang', '017-1112223', 'test@yahoo.com', FALSE);