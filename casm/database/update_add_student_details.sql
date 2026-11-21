-- ============================================================
--  CASM update: student details + auto student ID
--
--  ONLY needed if you ALREADY imported an older casm.sql.
--  (A fresh import of the new casm.sql already includes these columns.)
--
--  phpMyAdmin > click database "casm_db" > SQL tab > paste > Go
--  Run it ONCE. If it says "Duplicate column name", the columns
--  already exist and you can ignore the message.
--  Your existing users, classes and submissions are not touched.
--
--  Existing students get a student ID automatically:
--  the year they registered + 6 random digits (e.g. 2026773856).
-- ============================================================
USE casm_db;

ALTER TABLE users
  ADD COLUMN student_id VARCHAR(20) NULL COMMENT 'students only: year registered + 6 random digits' AFTER avatar,
  ADD COLUMN phone VARCHAR(30) NULL AFTER student_id,
  ADD COLUMN ic_number VARCHAR(30) NULL COMMENT 'IC or passport number' AFTER phone,
  ADD COLUMN date_of_birth DATE NULL AFTER ic_number,
  ADD COLUMN gender VARCHAR(10) NULL AFTER date_of_birth,
  ADD COLUMN address VARCHAR(255) NULL AFTER gender,
  ADD COLUMN postcode VARCHAR(10) NULL AFTER address,
  ADD COLUMN city VARCHAR(80) NULL AFTER postcode,
  ADD COLUMN state VARCHAR(60) NULL AFTER city,
  ADD COLUMN programme VARCHAR(120) NULL COMMENT 'course of study' AFTER state,
  ADD COLUMN guardian_name VARCHAR(100) NULL AFTER programme,
  ADD COLUMN guardian_phone VARCHAR(30) NULL AFTER guardian_name;

-- give every existing student an ID: year registered + 6 random digits
UPDATE users
   SET student_id = CONCAT(YEAR(created_at), LPAD(FLOOR(RAND() * 1000000), 6, '0'))
 WHERE role = 'student' AND student_id IS NULL;

-- IDs must be unique (if this line errors with "Duplicate entry",
-- run the UPDATE above once more with "student_id IS NULL" replaced by "1 = 1", then run this line again)
ALTER TABLE users ADD UNIQUE KEY uq_users_student_id (student_id);
