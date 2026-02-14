-- Add display_name to users
ALTER TABLE users
  ADD COLUMN display_name VARCHAR(120) DEFAULT NULL AFTER username;
