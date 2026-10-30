CREATE TABLE refresh_tokens (
    id BIGINT NOT NULL AUTO_INCREMENT,
    token_hash CHAR(64) NOT NULL,
    user_id BIGINT NOT NULL,
    issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    revoked_reason VARCHAR(32) NULL,
    session_uuid VARCHAR(36) NOT NULL,
    started_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_refresh_tokens_token_hash (token_hash),
    KEY ix_refresh_tokens_user_id (user_id),
    KEY ix_refresh_tokens_session_uuid (session_uuid),
    CONSTRAINT fk_refresh_tokens_user_id FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
