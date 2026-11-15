# Goals and Non-goals

Purpose: define what Pidgey is, what it is not, and the scope it commits to.

## What Pidgey is

- A self-hostable email-delivery service written in plain PHP (no application framework).
- A JSON API that sends transactional email **synchronously**: the API request sends the
  message and returns the outcome in the response.
- A relay over **generic SMTP**: each organization configures its own SMTP connection(s).
- An administrative web UI (React) to manage organizations, users, SMTP connections and
  API tokens.

## What Pidgey is not

- Not an SMTP server or mail transfer agent; it does not receive or store-and-forward mail.
- Not a marketing/bulk email platform (no campaigns, lists, or unsubscribe management).
- Not a queue-based delivery system; there are no background workers or retries in v1.
- Not tied to any specific provider API (SendGrid, SES, etc.); only generic SMTP.

## Goals

- Simple, auditable codebase: plain PHP, raw PDO, a single runtime dependency (`monolog/monolog`).
- Multi-organization: data and configuration isolated per organization.
- Scoped API tokens that can be created and revoked from the admin UI.
- Reproducible development and testing through Docker, with Mailpit as a fake SMTP server.
- Clear error reporting to API clients (see [email failures](../email/failures.md)).

## Non-goals (v1)

- Attachments and templates (see [message scope](../email/message-scope.md)).
- Asynchronous sending, retries, scheduling.
- Per-user roles or permissions inside an organization.
- Users belonging to more than one organization.
- Inbound email, bounce processing, open/click tracking.
