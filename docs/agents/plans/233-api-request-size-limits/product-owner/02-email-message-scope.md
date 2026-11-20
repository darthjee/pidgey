# Email message scope

Record the subject rules in the MIME spec.

- Validation: `subject` at most 255 characters → `subject must be shorter than or equal to 255 characters`. Note that with this cap no unbreakable run can reach RFC 5322's 998-character line limit.
- Headers table, `Subject` row: printable ASCII as-is **unless it contains `=?`**, in which case it is encoded as encoded-words.
- Body section: replace "An API body-size limit stays a pending item" with the decided 1 MiB body limit (link to sending.md) and no field cap on `text`/`html`.

## Files to Change

- `docs/agents/email/message-scope.md` — Validation, Headers and Body updates.
