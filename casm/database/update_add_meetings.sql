-- ============================================================
--  CASM update: Online Meetings
--
--  ONLY needed if you ALREADY imported casm.sql before this update.
--  (A fresh import of the new casm.sql already includes these tables.)
--
--  phpMyAdmin > click database "casm_db" > SQL tab > paste > Go
--  Safe to run more than once. Your existing data is not touched.
-- ============================================================
USE casm_db;

CREATE TABLE IF NOT EXISTS meetings (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_id    INT UNSIGNED NOT NULL,
  title        VARCHAR(150) NOT NULL,
  description  TEXT NULL,
  room_code    VARCHAR(40)  NOT NULL,
  custom_link  VARCHAR(255) NULL,
  scheduled_at DATETIME NOT NULL,
  status       ENUM('scheduled','live','ended') NOT NULL DEFAULT 'scheduled',
  started_at   DATETIME NULL,
  ended_at     DATETIME NULL,
  created_by   INT UNSIGNED NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_meetings_room (room_code),
  KEY idx_meetings_course (class_id),
  KEY idx_meetings_when (scheduled_at),
  CONSTRAINT fk_meetings_course FOREIGN KEY (class_id)  REFERENCES classes(id) ON DELETE CASCADE,
  CONSTRAINT fk_meetings_user   FOREIGN KEY (created_by) REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meeting_attendance (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  meeting_id INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  joined_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_attendance (meeting_id, user_id),
  KEY idx_attendance_user (user_id),
  CONSTRAINT fk_attend_meeting FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
  CONSTRAINT fk_attend_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
