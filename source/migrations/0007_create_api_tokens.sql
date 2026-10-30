CREATE TABLE api_tokens (
    id BIGINT NOT NULL AUTO_INCREMENT,
    organization_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    token_prefix VARCHAR(16) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    scopes JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    revoked_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_tokens_token_hash (token_hash),
    KEY ix_api_tokens_organization_id (organization_id),
    CONSTRAINT fk_api_tokens_organization_id FOREIGN KEY (organization_id)
        REFERENCES organizations (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
