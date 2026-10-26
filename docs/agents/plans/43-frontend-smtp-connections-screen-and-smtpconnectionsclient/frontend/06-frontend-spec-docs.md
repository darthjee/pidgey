# Frontend spec docs
Record what is now implemented. Do not change the API specs.

- `api-compatibility.md`: mark the seven M6 `/smtp-connections*` rows as implemented by
  `SmtpConnectionsClient`; note that `ApiError` carries `error.smtp`.
- `layout.md`: `SmtpConnectionsClient` is implemented (drop "#43" as future work), mention the
  `smtpConnections` resource folder and `ApiError.smtp`.
- `screens.md`: move `#/smtp-connections` out of "Pidgey screens to come" into the routes table
  with `#/smtp-connections/new` and `#/smtp-connections/:id/edit`; add an "SMTP connections
  screens" section (list, row actions, test-email modal, delete warning, form rules and
  write-only password); the Organization dropdown lists **Settings** and **SMTP connections**.

## Files to Change
- `docs/agents/specs/frontend/api-compatibility.md`
- `docs/agents/specs/frontend/layout.md`
- `docs/agents/specs/frontend/screens.md`
