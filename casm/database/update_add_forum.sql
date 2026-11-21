-- ============================================================
--  CASM update: Forum (lecturer creates topics, students reply)
--
--  ONLY needed if you ALREADY imported casm.sql before this update.
--  (A fresh import of the new casm.sql already includes these tables.)
--
--  phpMyAdmin > click database "casm_db" > SQL tab > paste > Go
--  Safe to run more than once. Your existing data is not touched.
-- ============================================================
USE casm_db;

CREATE TABLE IF NOT EXISTS forum_topics (
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

CREATE TABLE IF NOT EXISTS forum_replies (
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
