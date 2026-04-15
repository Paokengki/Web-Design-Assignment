-- Add Profile_Image column to User table if it doesn't exist
ALTER TABLE User ADD COLUMN Profile_Image VARCHAR(255) AFTER Email;
