-- ============================================================
--  CASM  -  Classroom, Assignment & Study Manager
--  Database: casm_db   (import this file in phpMyAdmin)
--
--  phpMyAdmin > Import > Choose file > casm.sql > Go
--  (If your host does not allow CREATE DATABASE, create an empty
--   database called casm_db first, select it, then delete the two
--   lines CREATE DATABASE / USE below and import again.)
--
--  Demo logins (password in brackets)
--    admin@casm.test        (admin123)
--    lecturer1@casm.test    (lecturer123)   Dr. Aisyah Rahman
--    lecturer2@casm.test    (lecturer123)   Mr. Daniel Tan
--    student1@casm.test ... student8@casm.test  (student123)
-- ============================================================

CREATE DATABASE IF NOT EXISTS casm_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE casm_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS forum_replies;
DROP TABLE IF EXISTS forum_topics;
DROP TABLE IF EXISTS attendance_records;
DROP TABLE IF EXISTS attendance_sessions;
DROP TABLE IF EXISTS meeting_attendance;
DROP TABLE IF EXISTS meetings;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS doubt_replies;
DROP TABLE IF EXISTS doubt_votes;
DROP TABLE IF EXISTS doubts;
DROP TABLE IF EXISTS submissions;
DROP TABLE IF EXISTS assignments;
DROP TABLE IF EXISTS materials;
DROP TABLE IF EXISTS enrollments;
DROP TABLE IF EXISTS classes;
DROP TABLE IF EXISTS courses;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- 1. USERS  (Administrator / Lecturer / Student)
-- ------------------------------------------------------------
CREATE TABLE users (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(100) NOT NULL,
  email       VARCHAR(120) NOT NULL,
  password    VARCHAR(255) NOT NULL,
  role        ENUM('admin','lecturer','student') NOT NULL DEFAULT 'student',
  avatar      VARCHAR(255) NULL COMMENT 'profile picture path inside uploads/',
  student_id  VARCHAR(20)  NULL COMMENT 'students only: year registered + 6 random digits, e.g. 2026773856',
  phone       VARCHAR(30)  NULL,
  ic_number   VARCHAR(30)  NULL COMMENT 'IC or passport number',
  date_of_birth DATE NULL,
  gender      VARCHAR(10)  NULL,
  address     VARCHAR(255) NULL,
  postcode    VARCHAR(10)  NULL,
  city        VARCHAR(80)  NULL,
  state       VARCHAR(60)  NULL,
  programme   VARCHAR(120) NULL COMMENT 'course of study',
  guardian_name  VARCHAR(100) NULL,
  guardian_phone VARCHAR(30)  NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_student_id (student_id),
  KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. COURSES  (created by the administrator, one lecturer each)
--    A course holds one or more CLASSES (Class A, Class B, ...)
-- ------------------------------------------------------------
CREATE TABLE courses (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code         VARCHAR(20)  NOT NULL,
  title        VARCHAR(150) NOT NULL,
  description  TEXT NULL,
  lecturer_id  INT UNSIGNED NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_courses_code (code),
  KEY idx_courses_lecturer (lecturer_id),
  CONSTRAINT fk_courses_lecturer FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2b. CLASSES (Class A, B, C ... inside a course)
--     Students join a class with its join code. Notes, assignments,
--     meetings, attendance ... all belong to a class.
--     code  = course code + class name (e.g. CS201-A)
--     title = course title (kept in sync when the course is edited)
-- ------------------------------------------------------------
CREATE TABLE classes (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  course_id    INT UNSIGNED NOT NULL,
  class_name   VARCHAR(20)  NOT NULL COMMENT 'A, B, C ...',
  code         VARCHAR(30)  NOT NULL,
  title        VARCHAR(150) NOT NULL,
  description  TEXT NULL,
  lecturer_id  INT UNSIGNED NOT NULL,
  join_code    VARCHAR(12)  NOT NULL,
  day_of_week  TINYINT UNSIGNED NULL COMMENT '1=Monday ... 7=Sunday',
  start_time   TIME NULL,
  end_time     TIME NULL,
  room         VARCHAR(60) NULL,
  color        VARCHAR(20) NOT NULL DEFAULT 'lavender',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_classes_code (code),
  UNIQUE KEY uq_classes_join (join_code),
  UNIQUE KEY uq_classes_name (course_id, class_name),
  KEY idx_classes_lecturer (lecturer_id),
  CONSTRAINT fk_classes_course   FOREIGN KEY (course_id)   REFERENCES courses(id) ON DELETE CASCADE,
  CONSTRAINT fk_classes_lecturer FOREIGN KEY (lecturer_id) REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. ENROLLMENTS (student <-> class)
-- ------------------------------------------------------------
CREATE TABLE enrollments (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_id   INT UNSIGNED NOT NULL,
  student_id  INT UNSIGNED NOT NULL,
  enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_enroll (class_id, student_id),
  KEY idx_enroll_student (student_id),
  CONSTRAINT fk_enroll_course  FOREIGN KEY (class_id)  REFERENCES classes(id) ON DELETE CASCADE,
  CONSTRAINT fk_enroll_student FOREIGN KEY (student_id) REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. MATERIALS (notes / slides uploaded by the lecturer)
-- ------------------------------------------------------------
CREATE TABLE materials (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_id     INT UNSIGNED NOT NULL,
  title         VARCHAR(150) NOT NULL,
  description   TEXT NULL,
  file_path     VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  uploaded_by   INT UNSIGNED NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_materials_course (class_id),
  CONSTRAINT fk_materials_course FOREIGN KEY (class_id)   REFERENCES classes(id) ON DELETE CASCADE,
  CONSTRAINT fk_materials_user   FOREIGN KEY (uploaded_by) REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. ASSIGNMENTS
-- ------------------------------------------------------------
CREATE TABLE assignments (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_id   INT UNSIGNED NOT NULL,
  title       VARCHAR(150) NOT NULL,
  description TEXT NULL,
  due_date    DATETIME NOT NULL,
  max_marks   INT UNSIGNED NOT NULL DEFAULT 100,
  created_by  INT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_assign_course (class_id),
  KEY idx_assign_due (due_date),
  CONSTRAINT fk_assign_course FOREIGN KEY (class_id)  REFERENCES classes(id) ON DELETE CASCADE,
  CONSTRAINT fk_assign_user   FOREIGN KEY (created_by) REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. SUBMISSIONS (one row per student per assignment)
-- ------------------------------------------------------------
CREATE TABLE submissions (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  assignment_id INT UNSIGNED NOT NULL,
  student_id    INT UNSIGNED NOT NULL,
  file_path     VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  note          TEXT NULL,
  submitted_at  DATETIME NOT NULL,
  marks         DECIMAL(6,2) NULL,
  feedback      TEXT NULL,
  graded_at     DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_submission (assignment_id, student_id),
  KEY idx_sub_student (student_id),
  CONSTRAINT fk_sub_assign  FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
  CONSTRAINT fk_sub_student FOREIGN KEY (student_id)    REFERENCES users(id)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. DOUBT BOARD (questions + replies)
--    The asker's name is shown. Students in the class and the
--    lecturer can reply to a question (doubt_replies).
-- ------------------------------------------------------------
CREATE TABLE doubts (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_id   INT UNSIGNED NOT NULL,
  student_id  INT UNSIGNED NOT NULL,
  question    TEXT NOT NULL,
  is_resolved TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_doubts_course (class_id),
  CONSTRAINT fk_doubts_course  FOREIGN KEY (class_id)  REFERENCES classes(id) ON DELETE CASCADE,
  CONSTRAINT fk_doubts_student FOREIGN KEY (student_id) REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE doubt_replies (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  doubt_id   INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  body       TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_replies_doubt (doubt_id),
  CONSTRAINT fk_replies_doubt FOREIGN KEY (doubt_id) REFERENCES doubts(id) ON DELETE CASCADE,
  CONSTRAINT fk_replies_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 8. CALENDAR EVENTS (quizzes, exams, tasks ... set by lecturers)
--    Assignment deadlines are read from the assignments table.
-- ------------------------------------------------------------
CREATE TABLE events (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_id   INT UNSIGNED NOT NULL,
  title       VARCHAR(150) NOT NULL,
  event_type  ENUM('assignment','quiz','task','exam','other') NOT NULL DEFAULT 'other',
  event_date  DATE NOT NULL,
  start_time  TIME NULL,
  description TEXT NULL,
  created_by  INT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_events_date (event_date),
  KEY idx_events_course (class_id),
  CONSTRAINT fk_events_course FOREIGN KEY (class_id)  REFERENCES classes(id) ON DELETE CASCADE,
  CONSTRAINT fk_events_user   FOREIGN KEY (created_by) REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 9. NOTIFICATIONS
-- ------------------------------------------------------------
CREATE TABLE notifications (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  title      VARCHAR(150) NOT NULL,
  message    VARCHAR(300) NOT NULL,
  link       VARCHAR(255) NULL,
  is_read    TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_user (user_id, is_read),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 10. ACTIVITY LOGS (audit trail for the administrator)
--     user_name is a snapshot so the log survives account deletion.
-- ------------------------------------------------------------
CREATE TABLE activity_logs (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NULL,
  user_name  VARCHAR(100) NOT NULL,
  role       VARCHAR(20)  NOT NULL,
  action     VARCHAR(40)  NOT NULL,
  details    VARCHAR(255) NULL,
  ip_address VARCHAR(45)  NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_logs_created (created_at),
  KEY idx_logs_action (action),
  CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- 12. FORUM (only the lecturer starts a topic; students reply)
-- ------------------------------------------------------------
CREATE TABLE forum_topics (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  class_id  INT UNSIGNED NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  title      VARCHAR(150) NOT NULL,
  body       TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_forum_course (class_id),
  KEY idx_forum_created (created_at),
  CONSTRAINT fk_forum_course FOREIGN KEY (class_id)  REFERENCES classes(id) ON DELETE CASCADE,
  CONSTRAINT fk_forum_user   FOREIGN KEY (created_by) REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE forum_replies (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  topic_id   INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  body       TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_forum_replies_topic (topic_id),
  CONSTRAINT fk_freply_topic FOREIGN KEY (topic_id) REFERENCES forum_topics(id) ON DELETE CASCADE,
  CONSTRAINT fk_freply_user  FOREIGN KEY (user_id)  REFERENCES users(id)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 11. ONLINE MEETINGS (video meetings run by lecturers)
--     custom_link = the Google Meet link (https://meet.google.com/xxx-xxxx-xxx)
--     room_code   = internal unique id of the meeting
--     status      = scheduled -> live (lecturer starts) -> ended
-- ------------------------------------------------------------
CREATE TABLE meetings (
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

-- who clicked "Join" (attendance) - one row per person per meeting
CREATE TABLE meeting_attendance (
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


-- ------------------------------------------------------------
-- CLASS ATTENDANCE
--   attendance_sessions : one row each time a lecturer opens attendance for a class
--   attendance_records  : one row per student per session (present / absent / absent_permission)
--   (separate from meeting_attendance, which only records who joined an online meeting)
-- ------------------------------------------------------------
CREATE TABLE attendance_sessions (
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

CREATE TABLE attendance_records (
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


-- ============================================================
--  SAMPLE DATA
-- ============================================================

-- Users ------------------------------------------------------
INSERT INTO users (id, name, email, password, role, is_active, created_at) VALUES
(1,  'System Administrator', 'admin@casm.test',     '$2y$10$uVPXXBANj2CH.f0K2rgDG.qLnYyFXvyV3lFzPQusJO7HLWUCAy04K', 'admin',    1, '2026-09-01 08:00:00'),
(2,  'Dr. Aisyah Rahman',    'lecturer1@casm.test', '$2y$10$fi91U3QvUNBfiJ2GRuZl0uhnQ6d8vbLmzCK548gigZXCiz5EdVPRK', 'lecturer', 1, '2026-09-01 08:05:00'),
(3,  'Mr. Daniel Tan',       'lecturer2@casm.test', '$2y$10$fi91U3QvUNBfiJ2GRuZl0uhnQ6d8vbLmzCK548gigZXCiz5EdVPRK', 'lecturer', 1, '2026-09-01 08:06:00'),
(4,  'Nur Aina Sofea',       'student1@casm.test',  '$2y$10$UjMRzMTjUbBS/1EEYZlR/elYy/XEL705zQtp7mQYqgRbEy/Oc43w.', 'student',  1, '2026-09-02 09:00:00'),
(5,  'Muhammad Haziq',       'student2@casm.test',  '$2y$10$UjMRzMTjUbBS/1EEYZlR/elYy/XEL705zQtp7mQYqgRbEy/Oc43w.', 'student',  1, '2026-09-02 09:01:00'),
(6,  'Siti Nabila',          'student3@casm.test',  '$2y$10$UjMRzMTjUbBS/1EEYZlR/elYy/XEL705zQtp7mQYqgRbEy/Oc43w.', 'student',  1, '2026-09-02 09:02:00'),
(7,  'Lim Wei Jie',          'student4@casm.test',  '$2y$10$UjMRzMTjUbBS/1EEYZlR/elYy/XEL705zQtp7mQYqgRbEy/Oc43w.', 'student',  1, '2026-09-02 09:03:00'),
(8,  'Priya Nair',           'student5@casm.test',  '$2y$10$UjMRzMTjUbBS/1EEYZlR/elYy/XEL705zQtp7mQYqgRbEy/Oc43w.', 'student',  1, '2026-09-02 09:04:00'),
(9,  'Ahmad Irfan',          'student6@casm.test',  '$2y$10$UjMRzMTjUbBS/1EEYZlR/elYy/XEL705zQtp7mQYqgRbEy/Oc43w.', 'student',  1, '2026-09-02 09:05:00'),
(10, 'Farah Amira',          'student7@casm.test',  '$2y$10$UjMRzMTjUbBS/1EEYZlR/elYy/XEL705zQtp7mQYqgRbEy/Oc43w.', 'student',  1, '2026-09-02 09:06:00'),
(11, 'Hafiz Ismail',         'student8@casm.test',  '$2y$10$UjMRzMTjUbBS/1EEYZlR/elYy/XEL705zQtp7mQYqgRbEy/Oc43w.', 'student',  1, '2026-09-02 09:07:00');

-- Administrator and lecturer details (phone, IC, address ...)
UPDATE users SET phone='03-77412200', ic_number='800101-14-5001', date_of_birth='1980-01-01', gender='male', address='Level 2, Block A, CASM Campus, Jalan Universiti', postcode='46200', city='Petaling Jaya', state='Selangor' WHERE email='admin@casm.test' AND address IS NULL;
UPDATE users SET phone='012-9081234', ic_number='850615-10-5342', date_of_birth='1985-06-15', gender='female', address='18 Jalan SS 15/4, Subang Jaya', postcode='47500', city='Subang Jaya', state='Selangor', programme='Faculty of Computing' WHERE email='lecturer1@casm.test' AND address IS NULL;
UPDATE users SET phone='016-2273345', ic_number='881103-14-5237', date_of_birth='1988-11-03', gender='male', address='22 Jalan Bukit Bintang 3, Taman Segar', postcode='55100', city='Kuala Lumpur', state='Kuala Lumpur', programme='Faculty of Computing' WHERE email='lecturer2@casm.test' AND address IS NULL;

-- Student details (student ID = year registered + 6 random digits) --
UPDATE users SET student_id='2026481937', phone='012-3456789', ic_number='050312-10-1234', date_of_birth='2005-03-12', gender='female', address='12 Jalan SS 2/55, SS 2', postcode='47300', city='Petaling Jaya', state='Selangor', programme='Diploma in Information Technology', guardian_name='Rahman Ismail', guardian_phone='013-4567890' WHERE id=4;
UPDATE users SET student_id='2026605218', phone='011-2345678', ic_number='040927-14-5521', date_of_birth='2004-09-27', gender='male', address='8 Lorong Bunga Raya 3', postcode='50480', city='Kuala Lumpur', state='Kuala Lumpur', programme='Diploma in Information Technology', guardian_name='Zainal Abidin', guardian_phone='012-8765432' WHERE id=5;
UPDATE users SET student_id='2026732094', phone='016-7788990', ic_number='050118-12-0846', date_of_birth='2005-01-18', gender='female', address='45 Jalan Mawar 5, Taman Mawar', postcode='43000', city='Kajang', state='Selangor', programme='Diploma in Information Technology', guardian_name='Hassan Yusof', guardian_phone='019-2233445' WHERE id=6;
UPDATE users SET student_id='2026158463', phone='017-6655443', ic_number='041105-10-7712', date_of_birth='2004-11-05', gender='male', address='23 Jalan Kenari 8, Bandar Puchong Jaya', postcode='47100', city='Puchong', state='Selangor', programme='Diploma in Information Technology', guardian_name='Lim Kok Seng', guardian_phone='012-3344556' WHERE id=7;
UPDATE users SET student_id='2026894721', phone='014-9988776', ic_number='050622-08-2208', date_of_birth='2005-06-22', gender='female', address='10 Jalan Teratai 2, Taman Sri Muda', postcode='40400', city='Shah Alam', state='Selangor', programme='Diploma in Information Technology', guardian_name='Suresh Nair', guardian_phone='016-5544332' WHERE id=8;
UPDATE users SET student_id='2026317659', phone='018-2211334', ic_number='040214-05-4437', date_of_birth='2004-02-14', gender='male', address='67 Jalan Melati 1, Taman Melati', postcode='53100', city='Kuala Lumpur', state='Kuala Lumpur', programme='Diploma in Information Technology', guardian_name='Ismail Hamid', guardian_phone='013-9988001' WHERE id=9;
UPDATE users SET student_id='2026560382', phone='019-4455667', ic_number='050830-14-6120', date_of_birth='2005-08-30', gender='female', address='5 Jalan Dahlia 4, Taman Bukit Indah', postcode='81200', city='Johor Bahru', state='Johor', programme='Diploma in Information Technology', guardian_name='Amir Hamzah', guardian_phone='011-7766554' WHERE id=10;
UPDATE users SET student_id='2026249105', phone='010-5566778', ic_number='040509-11-3359', date_of_birth='2004-05-09', gender='male', address='31 Jalan Cempaka 6, Taman Desa', postcode='58100', city='Kuala Lumpur', state='Kuala Lumpur', programme='Diploma in Information Technology', guardian_name='Ismail Hashim', guardian_phone='012-1100223' WHERE id=11;

-- Courses (2 courses, one lecturer each) -----------------------
INSERT INTO courses (id, code, title, description, lecturer_id, created_at) VALUES
(1, 'CS201', 'Database Systems', 'Relational model, ER modelling, normalisation and SQL.',                  2, '2026-09-05 10:00:00'),
(2, 'CS204', 'Data Structures',  'Arrays, linked lists, stacks, queues, trees and basic algorithms.',      3, '2026-09-05 10:30:00');

-- Classes (A, B and C inside each course) ----------------------
INSERT INTO classes (id, course_id, class_name, code, title, description, lecturer_id, join_code, day_of_week, start_time, end_time, room, color, created_at) VALUES
(1, 1, 'A', 'CS201-A', 'Database Systems', 'Relational model, ER modelling, normalisation and SQL.',             2, 'DB2026',  1, '09:00:00', '11:00:00', 'Lab 2',      'lavender', '2026-09-05 10:00:00'),
(2, 1, 'B', 'CS201-B', 'Database Systems', 'Relational model, ER modelling, normalisation and SQL.',             2, 'DB2026B', 3, '09:00:00', '11:00:00', 'Lab 2',      'lavender', '2026-09-05 10:01:00'),
(3, 1, 'C', 'CS201-C', 'Database Systems', 'Relational model, ER modelling, normalisation and SQL.',             2, 'DB2026C', 5, '09:00:00', '11:00:00', 'Lab 3',      'lavender', '2026-09-05 10:02:00'),
(4, 2, 'A', 'CS204-A', 'Data Structures',  'Arrays, linked lists, stacks, queues, trees and basic algorithms.',  3, 'DS2026',  4, '08:00:00', '10:00:00', 'Room A1-05', 'peach',    '2026-09-05 10:30:00'),
(5, 2, 'B', 'CS204-B', 'Data Structures',  'Arrays, linked lists, stacks, queues, trees and basic algorithms.',  3, 'DS2026B', 1, '14:00:00', '16:00:00', 'Room A1-06', 'peach',    '2026-09-05 10:31:00'),
(6, 2, 'C', 'CS204-C', 'Data Structures',  'Arrays, linked lists, stacks, queues, trees and basic algorithms.',  3, 'DS2026C', 3, '14:00:00', '16:00:00', 'Room A1-05', 'peach',    '2026-09-05 10:32:00');

-- Enrollments (a student joins one class per course) ---------
INSERT INTO enrollments (class_id, student_id, enrolled_at) VALUES
(1, 4, '2026-09-08 09:00:00'), (1, 5, '2026-09-08 09:00:00'), (1, 6, '2026-09-08 09:00:00'),
(1, 7, '2026-09-08 09:00:00'), (1, 8, '2026-09-08 09:00:00'), (1, 9, '2026-09-08 09:00:00'),
(2, 10, '2026-09-08 09:05:00'), (2, 11, '2026-09-08 09:05:00'),
(4, 7, '2026-09-08 09:15:00'), (4, 8, '2026-09-08 09:15:00'), (4, 9, '2026-09-08 09:15:00'),
(4, 10, '2026-09-08 09:15:00'), (4, 11, '2026-09-08 09:15:00'),
(5, 4, '2026-09-08 09:20:00'), (5, 5, '2026-09-08 09:20:00'),
(6, 6, '2026-09-08 09:25:00');

-- Materials (sample files live in uploads/materials) ---------
INSERT INTO materials (id, class_id, title, description, file_path, original_name, uploaded_by, created_at) VALUES
(1, 1, 'Week 1 - Introduction to Databases', 'Overview of DBMS, data models and why we use databases.', 'materials/sample_notes.txt', 'week1_intro_databases.txt', 2, '2026-09-10 10:00:00'),
(2, 1, 'Week 2 - ER Modelling',              'Entities, attributes, relationships and cardinality.',      'materials/sample_notes.txt', 'week2_er_modelling.txt',    2, '2026-09-17 10:00:00'),
(5, 4, 'Linked Lists Notes',                 'Singly and doubly linked lists with examples.',              'materials/sample_notes.txt', 'linked_lists.txt',          3, '2026-10-02 09:00:00');

-- Assignments ------------------------------------------------
--  Two assignments are due on 20 Nov 2026 so you can test the
--  Smart Workload Balancer (CS204-A has one too).
INSERT INTO assignments (id, class_id, title, description, due_date, max_marks, created_by, created_at) VALUES
(1, 1, 'ER Diagram Design',            'Design an ER diagram for a library system. Submit as PDF.',                       '2026-11-20 23:59:00', 100, 2, '2026-11-02 10:00:00'),
(2, 1, 'SQL Queries Lab',              'Complete the 15 SQL questions in the lab sheet. Submit your .sql file.',          '2026-11-27 23:59:00', 50,  2, '2026-11-05 10:00:00'),
(5, 4, 'Linked List Implementation',   'Implement a singly linked list with insert, delete and search operations.',       '2026-11-09 23:59:00', 100, 3, '2026-10-26 09:00:00'),
(6, 4, 'Stack and Queue Exercise',     'Solve the five stack and queue problems in the worksheet.',                       '2026-11-30 23:59:00', 60,  3, '2026-11-08 09:00:00'),
(7, 4, 'Binary Tree Worksheet',      'Draw and traverse the binary trees in the worksheet (in-order, pre-order, post-order).', '2026-11-20 17:00:00', 50,  3, '2026-11-12 09:00:00');

-- Submissions (sample file lives in uploads/submissions) -----
INSERT INTO submissions (assignment_id, student_id, file_path, original_name, note, submitted_at, marks, feedback, graded_at) VALUES
(1, 4,  'submissions/sample_submission.txt', 'library_er_diagram.txt', NULL, '2026-11-11 14:20:00', NULL, NULL, NULL),
(1, 5,  'submissions/sample_submission.txt', 'er_diagram_haziq.txt',   NULL, '2026-11-12 08:05:00', NULL, NULL, NULL),
(5, 7,  'submissions/sample_submission.txt', 'linkedlist_weijie.txt',  NULL, '2026-11-08 16:30:00', 85, 'Good work. Remember to handle the empty list case in delete().', '2026-11-10 11:00:00'),
(5, 8,  'submissions/sample_submission.txt', 'linkedlist_priya.txt',   NULL, '2026-11-09 20:15:00', NULL, NULL, NULL),
(5, 9,  'submissions/sample_submission.txt', 'linkedlist_irfan.txt',   'Sorry for the late submission.', '2026-11-10 09:30:00', NULL, NULL, NULL);

-- Doubt board ------------------------------------------------
INSERT INTO doubts (id, class_id, student_id, question, is_resolved, created_at) VALUES
(1, 1, 5,  'Can someone explain the difference between 1NF, 2NF and 3NF again? I keep mixing up partial and transitive dependency.', 0, '2026-11-09 20:10:00'),
(2, 1, 6,  'When should we use a composite key and when should we add a surrogate ID instead?',                                       0, '2026-11-10 11:30:00'),
(3, 1, 8,  'I do not understand how ON DELETE CASCADE works with foreign keys.',                                                      0, '2026-11-11 09:45:00'),
(6, 4, 11, 'Why do we need a tail pointer in a linked list?',                                                                         1, '2026-11-05 10:00:00');

INSERT INTO doubt_replies (doubt_id, user_id, body, created_at) VALUES
(1, 6, 'Partial dependency = a non-key column depends on only PART of a composite key (2NF). Transitive = a non-key column depends on another non-key column (3NF).', '2026-11-09 21:00:00'),
(1, 2, 'Good explanation, Siti. I will go through a worked example at the start of Thursday\'s class.',                                                          '2026-11-10 08:15:00'),
(3, 2, 'ON DELETE CASCADE means that when the parent row is deleted, every child row pointing to it is deleted automatically. See the Week 4 notes for an example.', '2026-11-11 14:00:00'),
(6, 3, 'The tail pointer lets you append at the end in O(1) instead of walking the whole list.',                                                                 '2026-11-05 12:00:00');

-- Calendar events --------------------------------------------
INSERT INTO events (id, class_id, title, event_type, event_date, start_time, description, created_by) VALUES
(1, 1, 'Quiz 1: Normalisation',   'quiz', '2026-11-16', '09:00:00', 'Covers Week 3 and Week 4. Closed book, 30 minutes.', 2),
(3, 4, 'Data Structures Quiz',    'quiz', '2026-11-19', '08:00:00', 'Linked lists and stacks.',                            3),
(5, 1, 'Final Exam',              'exam', '2026-12-07', '09:00:00', 'Full syllabus.',                                      2);

-- Online meetings --------------------------------------------
--  #1 is already finished (with attendance); #3 is upcoming.
--  Log in as a lecturer and press "Start meeting" to try it live.
INSERT INTO meetings (id, class_id, title, description, room_code, custom_link, scheduled_at, status, started_at, ended_at, created_by, created_at) VALUES
(1, 1, 'Revision: Normalisation',      'Online revision session before Quiz 1. Bring your questions on 1NF, 2NF and 3NF.', 'CASM-cs201-7f3a9c21d0', 'https://meet.google.com/abc-defg-hij', '2026-11-15 20:00:00', 'ended',     '2026-11-15 20:03:00', '2026-11-15 21:10:00', 2, '2026-11-12 09:00:00'),
(3, 4, 'Data Structures Doubt Clinic', 'Open session for your linked list and stack questions.',                          'CASM-cs204-e8d015ab46', 'https://meet.google.com/abc-defg-hij', '2026-11-19 16:00:00', 'scheduled', NULL, NULL, 3, '2026-11-16 10:30:00');

INSERT INTO meeting_attendance (meeting_id, user_id, joined_at) VALUES
(1, 4, '2026-11-15 20:03:00'), (1, 5, '2026-11-15 20:05:00'), (1, 6, '2026-11-15 20:09:00'), (1, 8, '2026-11-15 20:21:00');

-- Notifications ----------------------------------------------
INSERT INTO notifications (user_id, title, message, link, is_read, created_at) VALUES
(4, 'New assignment',    'ER Diagram Design was posted in CS201 Database Systems. Due 20 Nov 2026.',   'student/assignment_view.php?id=1', 0, '2026-11-02 10:00:00'),
(4, 'Quiz scheduled',    'Quiz 1: Normalisation is scheduled on 16 Nov 2026.',                           'student/calendar.php',             0, '2026-11-09 09:00:00'),
(7, 'Assignment graded', 'Your Linked List Implementation was graded: 85 / 100.',                      'student/assignment_view.php?id=5', 0, '2026-11-10 11:00:00'),
(2, 'New submission',    'Nur Aina Sofea submitted ER Diagram Design.',                                 'lecturer/assignment_view.php?id=1', 0, '2026-11-11 14:20:00');

-- Activity logs ----------------------------------------------
INSERT INTO activity_logs (user_id, user_name, role, action, details, ip_address, created_at) VALUES
(1, 'System Administrator', 'admin',    'LOGIN',             'Signed in',                                          '127.0.0.1', '2026-11-09 08:00:00'),
(2, 'Dr. Aisyah Rahman',    'lecturer', 'UPLOAD_MATERIAL',   'Uploaded "Week 2 - ER Modelling" to CS201',          '127.0.0.1', '2026-09-17 10:00:00'),
(2, 'Dr. Aisyah Rahman',    'lecturer', 'CREATE_ASSIGNMENT', 'Created "ER Diagram Design" in CS201',               '127.0.0.1', '2026-11-02 10:00:00'),
(3, 'Mr. Daniel Tan',       'lecturer', 'CREATE_ASSIGNMENT', 'Created "Linked List Implementation" in CS204',      '127.0.0.1', '2026-10-26 09:00:00'),
(7, 'Lim Wei Jie',          'student',  'SUBMIT_ASSIGNMENT', 'Submitted "Linked List Implementation" (CS204)',     '127.0.0.1', '2026-11-08 16:30:00'),
(8, 'Priya Nair',           'student',  'SUBMIT_ASSIGNMENT', 'Submitted "Linked List Implementation" (CS204)',     '127.0.0.1', '2026-11-09 20:15:00'),
(9, 'Ahmad Irfan',          'student',  'SUBMIT_ASSIGNMENT', 'Submitted "Linked List Implementation" (CS204) - late', '127.0.0.1', '2026-11-10 09:30:00'),
(3, 'Mr. Daniel Tan',       'lecturer', 'GRADE_SUBMISSION',  'Graded Lim Wei Jie for "Linked List Implementation"', '127.0.0.1', '2026-11-10 11:00:00'),
(4, 'Nur Aina Sofea',       'student',  'SUBMIT_ASSIGNMENT', 'Submitted "ER Diagram Design" (CS201)',              '127.0.0.1', '2026-11-11 14:20:00'),
(5, 'Muhammad Haziq',       'student',  'SUBMIT_ASSIGNMENT', 'Submitted "ER Diagram Design" (CS201)',              '127.0.0.1', '2026-11-12 08:05:00'),
(2, 'Dr. Aisyah Rahman',    'lecturer', 'CREATE_MEETING',    'Scheduled meeting "Revision: Normalisation" (CS201)', '127.0.0.1', '2026-11-12 09:00:00'),
(2, 'Dr. Aisyah Rahman',    'lecturer', 'START_MEETING',     'Started meeting "Revision: Normalisation" (CS201)',   '127.0.0.1', '2026-11-15 20:03:00'),
(4, 'Nur Aina Sofea',       'student',  'JOIN_MEETING',      'Joined meeting "Revision: Normalisation" (CS201)',    '127.0.0.1', '2026-11-15 20:03:00'),
(5, 'Muhammad Haziq',       'student',  'JOIN_MEETING',      'Joined meeting "Revision: Normalisation" (CS201)',    '127.0.0.1', '2026-11-15 20:05:00'),
(2, 'Dr. Aisyah Rahman',    'lecturer', 'END_MEETING',       'Ended meeting "Revision: Normalisation" (CS201)',     '127.0.0.1', '2026-11-15 21:10:00');


-- Class attendance (sample) -----------------------------------
--  Sessions 1-3 and 7 are finished.  Session 8 (CS204-A) is OPEN right now,
--  so log in as student (e.g. student4@casm.test) and tick it,
--  or log in as lecturer2@casm.test to review it.
INSERT INTO attendance_sessions (id, class_id, title, session_date, note, status, opened_at, closed_at, created_by, created_at) VALUES
(1, 1, 'Week 9: Normalisation', '2026-11-02', NULL, 'closed', '2026-11-02 09:00:00', '2026-11-02 11:00:00', 2, '2026-11-02 07:00:00'),
(2, 1, 'Week 10: SQL Joins', '2026-11-09', NULL, 'closed', '2026-11-09 09:00:00', '2026-11-09 11:00:00', 2, '2026-11-09 07:00:00'),
(3, 1, 'Week 11: Transactions', '2026-11-16', NULL, 'closed', '2026-11-16 09:00:00', '2026-11-16 11:00:00', 2, '2026-11-16 07:00:00'),
(7, 4, 'Week 10: Linked Lists', '2026-11-12', NULL, 'closed', '2026-11-12 08:00:00', '2026-11-12 10:00:00', 3, '2026-11-12 07:00:00'),
(8, 4, 'Week 11: Stacks and Queues', CURDATE(), 'Tick your attendance before the end of today.', 'open', NOW(), NULL, 3, NOW());

INSERT INTO attendance_records (session_id, student_id, status, reason, marked_by, marked_at) VALUES
(1, 4, 'present', NULL, 'student', '2026-11-02 09:03:00'),
(1, 5, 'present', NULL, 'student', '2026-11-02 09:04:00'),
(1, 6, 'present', NULL, 'student', '2026-11-02 09:05:00'),
(1, 7, 'present', NULL, 'student', '2026-11-02 09:06:00'),
(1, 8, 'present', NULL, 'student', '2026-11-02 09:07:00'),
(1, 9, 'absent', NULL, 'system', '2026-11-02 11:00:00'),
(2, 4, 'present', NULL, 'student', '2026-11-09 09:03:00'),
(2, 5, 'present', NULL, 'student', '2026-11-09 09:04:00'),
(2, 6, 'absent', NULL, 'system', '2026-11-09 11:00:00'),
(2, 7, 'present', NULL, 'student', '2026-11-09 09:06:00'),
(2, 8, 'absent_permission', 'Medical leave (MC provided)', 'lecturer', '2026-11-09 11:00:00'),
(2, 9, 'absent', NULL, 'system', '2026-11-09 11:00:00'),
(3, 4, 'present', NULL, 'student', '2026-11-16 09:03:00'),
(3, 5, 'present', NULL, 'student', '2026-11-16 09:04:00'),
(3, 6, 'present', NULL, 'student', '2026-11-16 09:05:00'),
(3, 7, 'absent', NULL, 'system', '2026-11-16 11:00:00'),
(3, 8, 'present', NULL, 'student', '2026-11-16 09:07:00'),
(3, 9, 'absent', NULL, 'system', '2026-11-16 11:00:00'),
(7, 7, 'present', NULL, 'student', '2026-11-12 08:03:00'),
(7, 8, 'present', NULL, 'student', '2026-11-12 08:04:00'),
(7, 9, 'absent', NULL, 'system', '2026-11-12 10:00:00'),
(7, 10, 'present', NULL, 'student', '2026-11-12 08:06:00'),
(7, 11, 'present', NULL, 'student', '2026-11-12 08:07:00');

-- Forum (sample topics from the lecturers, with student replies) ---
INSERT INTO forum_topics (id, class_id, created_by, title, body, created_at) VALUES
(1, 1, 2, 'Tips for the SQL Queries Lab', 'Please read the lab sheet before the next class. Share which of the 15 questions you find hardest and what you have tried so far, and we will go through them together.', '2026-11-12 09:30:00');

INSERT INTO forum_replies (topic_id, user_id, body, created_at) VALUES
(1, 4, 'Question 9 on JOIN with GROUP BY is the hardest for me.', '2026-11-12 11:05:00'),
(1, 5, 'Same here, and I also get confused with HAVING versus WHERE.', '2026-11-12 13:40:00'),
(1, 2, 'Good points. We will start with questions 9 and 12 in class.', '2026-11-12 16:20:00');
