-- ============================================================
--  CASM update: Doubt Board replies
--
--  ONLY needed if you ALREADY imported an older casm.sql.
--  (A fresh import of the new casm.sql already includes this table.)
--
--  phpMyAdmin > click database "casm_db" > SQL tab > paste > Go
--  Run it ONCE. Your existing questions are not touched.
-- ============================================================
USE casm_db;

CREATE TABLE IF NOT EXISTS doubt_replies (
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
