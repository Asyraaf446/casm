-- ============================================================
--  CASM update: Class Attendance
--
--  ONLY needed if you ALREADY imported casm.sql before this update.
--  (A fresh import of the new casm.sql already includes these tables.)
--
--  phpMyAdmin > click database "casm_db" > SQL tab > paste > Go
--  Safe to run more than once. Your existing data is not touched.
-- ============================================================
USE casm_db;

CREATE TABLE IF NOT EXISTS attendance_sessions (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_id    INT UNSIGNED NOT NULL,
  title        VARCHAR(150) NOT NULL,
  session_date DATE NOT NULL,
  note         VARCHAR(255) NULL,
  status       ENUM('pending','open','closed') NOT NULL DEFAULT 'pending',
  opened_at    DATETIME NULL,
  closed_at    DATETIME NULL,
  created_by   INT UNSIGNED NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attsess_course (class_id),
  KEY idx_attsess_date (session_date),
  KEY idx_attsess_status (status),
  CONSTRAINT fk_attsess_course FOREIGN KEY (class_id)  REFERENCES classes(id) ON DELETE CASCADE,
  CONSTRAINT fk_attsess_user   FOREIGN KEY (created_by) REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_records (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  status     ENUM('present','absent','absent_permission') NOT NULL,
  reason     VARCHAR(255) NULL,
  marked_by  ENUM('student','lecturer','system') NOT NULL DEFAULT 'student',
  marked_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_attrec (session_id, student_id),
  KEY idx_attrec_student (student_id),
  KEY idx_attrec_status (status),
  CONSTRAINT fk_attrec_session FOREIGN KEY (session_id) REFERENCES attendance_sessions(id) ON DELETE CASCADE,
  CONSTRAINT fk_attrec_student FOREIGN KEY (student_id) REFERENCES users(id)               ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
