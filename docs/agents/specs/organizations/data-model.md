# Data Model Sketch

Purpose: sketch the initial database tables; the authoritative schema is the SQL migrations.

```
organizations
  id               BIGINT PK
  name             VARCHAR
  created_at, updated_at

users
  id               BIGINT PK
  organization_id  BIGINT FK -> organizations.id
  username         VARCHAR UNIQUE
  password_hash    VARCHAR
  created_at, updated_at

api_tokens
  id               BIGINT PK
  organization_id  BIGINT FK -> organizations.id
  name             VARCHAR          -- label shown in the UI
  token_prefix     VARCHAR          -- non-secret prefix for identification
  token_hash       CHAR(64) UNIQUE  -- SHA-256 of the full token
  scopes           JSON             -- e.g. ["send"]
  created_at
  last_used_at     NULL
  revoked_at       NULL

smtp_connections
  id               BIGINT PK
  organization_id  BIGINT FK -> organizations.id
  name             VARCHAR
  host             VARCHAR
  port             INT
  encryption       ENUM('none','tls','ssl')
  username         VARCHAR NULL
  password         VARBINARY NULL   -- encrypted at rest (see email spec)
  from_address     VARCHAR
  active           BOOLEAN
  created_at, updated_at

schema_migrations
  version          VARCHAR PK
  applied_at
```

**Pending decisions:** whether `username` is an email address; storage format for
`scopes` (JSON vs. join table).
