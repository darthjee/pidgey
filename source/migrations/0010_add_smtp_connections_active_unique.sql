ALTER TABLE smtp_connections
    ADD COLUMN active_organization_id BIGINT GENERATED ALWAYS AS (IF(active, organization_id, NULL)) STORED,
    ADD UNIQUE INDEX uq_smtp_connections_active_organization_id (active_organization_id);
