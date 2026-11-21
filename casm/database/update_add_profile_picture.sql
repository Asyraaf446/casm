-- ============================================================
--  CASM update: profile pictures
--
--  ONLY needed if you ALREADY imported an older casm.sql.
--  (A fresh import of the new casm.sql already includes this column.)
--
--  phpMyAdmin > click database "casm_db" > SQL tab > paste > Go
--  Run it ONCE. If it says "Duplicate column name 'avatar'",
--  the column already exists and you can ignore the message.
--  Your existing users, classes and submissions are not touched.
-- ============================================================
USE casm_db;

ALTER TABLE users
  ADD COLUMN avatar VARCHAR(255) NULL COMMENT 'profile picture path inside uploads/' AFTER role;
