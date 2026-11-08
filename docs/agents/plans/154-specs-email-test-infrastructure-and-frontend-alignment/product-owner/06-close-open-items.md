# Close the implement-email open items

Once steps 01–05 are in the permanent specs, close the temporary files.

- **`implement-email/testing.md`:**
  - turn every `- [ ] **Pending decision:**` into a decided `- [x]` entry that summarizes
    the decision and links to the permanent spec: Mailpit with auth, TLS paths, the fake
    server, the Mailpit API and mailbox, CI and local, required coverage;
  - update the building block: the support class names (`FakeSmtpStream`,
    `FakeSmtpServer`, `Mailpit`, `CapturedLog`), the child-process fixtures,
    `source/tests/fixtures/tls/`, the three test instances as collaborators, the governing
    specs (no more `#smtp-development-and-tests` anchor), and "Open items: none";
  - add an **Implementation work** list for the infra and backend issues:
    - TLS fixtures and script;
    - compose services and `tests` env;
    - CircleCI containers and env (link `deployment/pipeline.md`);
    - development `.env` (`SMTP_ALLOW_PRIVATE_HOSTS=true`, drop `SMTP_HOST` / `SMTP_PORT`);
    - the support classes;
    - the coverage list.
- **`implement-email/frontend.md`:**
  - the three open items become decided entries:
    - the divergence list (deactivate, `429`, the client rules, the reply display, the help
      text, the compatibility table; and what matched: field names, envelope keys, status
      codes, the error envelope, no HTML rendering);
    - text only and wrapped, no truncation;
    - the definition of done;
  - add an **Implementation work** list for the frontend issue:
    - `SmtpConnectionsClient.deactivate`;
    - the Deactivate action;
    - the `FormRules` additions;
    - `text-break` on the reply;
    - the help text;
    - the shared fixtures;
    - `api-compatibility.md`;
    - the manual check;
  - mark "Open items: none".
- **`implement-email.md` (index):** update the status of the test infrastructure and
  frontend rows, if the index tracks it.
- **Final check:** grep `docs/agents/specs/implement-email/` for `Pending decision` (none
  left) and the specs for `SMTP_HOST` / `SMTP_PORT` (none left, outside history).

## Files to Change

- `docs/agents/specs/implement-email/testing.md` — decided entries, building block,
  implementation work.
- `docs/agents/specs/implement-email/frontend.md` — decided entries, implementation work.
- `docs/agents/specs/implement-email.md` — index status, if tracked.
