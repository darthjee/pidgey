# Authentication, Routing and Screens

Purpose: define how the admin UI authenticates, routes and which screens it has.

## Authentication

- Uses the backend's PHP session cookie; no tokens stored in the browser.
- On load, the app calls `GET /v1/session`; `401` → login screen.

## Routing

Hash-based routing (`#/...`), so Apache only needs to serve `index.html`.

| Route | Screen |
| --- | --- |
| `#/login` | Login form. |
| `#/register` | Registration (creates organization + first user). |
| `#/organization` | Organization settings, including SMTP connections list/create/edit/delete and setting the active connection. |
| `#/tokens` | API tokens: list, create (choose name and scopes; show the token once), revoke. |

**Pending decisions:** routing library vs. a small hand-written hash router;
admin user management screen (see the admin model in
[organizations/entities.md](../organizations/entities.md#admin)).
