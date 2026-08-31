CREATE TABLE IF NOT EXISTS chat_message_user_archives (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    message_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    archived_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_message_user_archive (message_id, user_id),
    KEY idx_user_archived_at (user_id, archived_at),
    CONSTRAINT fk_message_user_archive_message
        FOREIGN KEY (message_id) REFERENCES chat_messages (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_message_user_archive_user
        FOREIGN KEY (user_id) REFERENCES users_tbl (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
