# Rewrite screens.md
Rewrite `docs/agents/specs/frontend/screens.md` (title "Authentication, Routing and Screens";
keep the Purpose line) to describe the frontend as it is, plus the targets of the follow-ups.

## Authentication
- **Access token:** the JWT `access_token` cookie (HttpOnly, set by the API), sent with
  `credentials: 'same-origin'`. Link `api/authentication/access-token.md`.
- **Refresh token:** kept in `localStorage` under `pidgey_refresh_token` and sent in request
  bodies. Link `api/authentication/sessions.md`.
- **On load:** `POST /auth/status.json` runs only when a refresh token is stored. Its
  outcomes:
  - logged in, with the admin flag;
  - `status: "pending"`, which keeps the token, shows "awaiting approval" and re-checks on
    window focus;
  - anything else, which clears the token and shows the logged-out state.
- **On `401`:** refresh once, then retry once. If the retry gets a `401` again, or the
  refresh fails, clear the token and open the login modal.
- **On `403`** on admin screens: redirect home.
- **Single flight (target, #41):**
  - Within a tab, one in-flight refresh promise is shared by every request that got a `401`.
  - Across tabs, the refresh runs under a Web Locks API lock (`navigator.locks`). After
    taking the lock, re-read the stored token, and skip the refresh when another tab already
    rotated it.
  - Without `navigator.locks`, only the in-tab single flight applies.
  - Why: the API treats a re-presented rotated token as a replay and revokes every session.
- **XSS mitigations** (the refresh token is readable by script):
  - render through React escaping only;
  - no `dangerouslySetInnerHTML`;
  - no third-party scripts at runtime.

  The `localStorage` storage is a known limitation for v1.

## Routing
Hash routes, from `HashRouteResolver.js`:

| Route | Screen |
| --- | --- |
| `#/` | home |
| `#/recover-password?token=` | reset password |
| `#/admin/users` | admin users |
| `#/admin/users/:id/edit` | admin user edit |
| `#/account/my-account` | my account |
| `#/account/authorization-requests` | authorization requests |

Unknown routes fall back to home. Header links show according to the auth state (admin link
only for admins).

## Login modal
The modal has these modes:
- password login;
- register: needs an "Organization name" field, target #40;
- recover password;
- device login: create a request, show a countdown, poll until the request resolves.

## Pidgey screens (targets)
| Route | Screen |
| --- | --- |
| `#/organization` | organization (#42) |
| `#/smtp-connections` | SMTP connections: list, create/edit with write-only password, activate, test email, delete (#43) |
| `#/api-tokens` | API tokens: list including revoked tokens, create with scopes, token shown once and never stored, revoke (#44) |

## Pending decisions
- Remove the stale items "routing library vs. hand-written router" (settled: hand-written
  hash router) and "admin user management screen" (settled: it exists).
- Add **Pending decision:** a strict Content-Security-Policy for `/admin/` (infra), to reduce
  the XSS exposure of the stored refresh token.
- Add **Pending decision:** an `HttpOnly` refresh cookie instead of `localStorage` (cross-link
  to `sessions.md`).

## Files to Change
- `docs/agents/specs/frontend/screens.md` — full rewrite as above; drop the PHP session and
  `GET /v1/session` text.
