CREATE TABLE authorization_requests (
    id BIGINT NOT NULL AUTO_INCREMENT,
    uuid VARCHAR(36) NOT NULL,
    username VARCHAR(255) NOT NULL,
    user_id BIGINT NULL,
    status ENUM('open', 'approved', 'denied', 'logged', 'expired') NOT NULL DEFAULT 'open',
    poll_token_hash CHAR(64) NOT NULL,
    request_ip VARCHAR(45) NOT NULL,
    request_user_agent VARCHAR(512) NOT NULL,
    approved_by_user_id BIGINT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    resolved_at DATETIME NULL,
    logged_at DATETIME NULL,
    authorize_failed_attempts INT NOT NULL DEFAULT 0,
    authorize_locked_until DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_authorization_requests_uuid (uuid),
    UNIQUE KEY uq_authorization_requests_poll_token_hash (poll_token_hash),
    KEY ix_authorization_requests_user_id_status (user_id, status),
    KEY ix_authorization_requests_expires_at (expires_at),
    KEY ix_authorization_requests_request_ip_created_at (request_ip, created_at),
    KEY ix_authorization_requests_username_created_at (username, created_at),
    KEY ix_authorization_requests_approved_by_user_id (approved_by_user_id),
    CONSTRAINT fk_authorization_requests_user_id FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_authorization_requests_approved_by_user_id FOREIGN KEY (approved_by_user_id)
        REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
