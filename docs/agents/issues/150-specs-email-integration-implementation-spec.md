# Issue: Specs: email integration implementation spec

## Description
Write a temporary **implementation spec** for the email integration of epic #149: everything
needed to let an organization register and manage SMTP connections, and send a test email
through one.

### Context

The permanent specs already define the target behavior:

- `docs/agents/specs/email/smtp-connections.md`, `send-flow.md`, `failures.md`,
  `message-scope.md`, `testing.md`;
- `docs/agents/specs/api/endpoints/pidgey-admin/smtp-connections.md` (list, create, read,
  update, delete, activate, test email);
- `docs/agents/specs/organizations/data-model/smtp-connections.md`;
- `docs/agents/specs/frontend/screens.md#smtp-connections-screens`.

Already implemented: the `smtp_connections` table, `SmtpConnection` entity and
`SmtpConnectionRepository` (#89); cookie-session auth, gate, origin check and body validation
(#120); the frontend SMTP screens and `SmtpConnectionsClient` (#43, never run against a real
backend). Not implemented: password encryption, SMTP transport / send flow, the
`/smtp-connections*.json` handlers and routes.

## Problem
Epic #149 needs one shared place that ties the existing email, API, data-model and frontend specs to the implementation work and collects every open decision, so the follow-up spec issues (#151–#154) can settle them and the implementation issues can be defined afterwards. Today the open items are scattered across several spec files and the epic body.

## Expected Behavior
- `docs/agents/specs/implement-email.md` and its detail files exist, indexed in the hub.
- Every open item above appears in exactly one detail file as a Pending decision.
- No permanent spec is changed except the hub index.
- The hub row and every file carry the temporary marker above; `implement-email.md` states
  the lifecycle rules.

## Solution
### What to write

Create `docs/agents/specs/implement-email.md` (index) and, per the spec granularity rule,
detail files under `docs/agents/specs/implement-email/`. Index it in `docs/agents/specs.md`
with a note that it is temporary and removed at the end of epic #149.

The spec must:

1. **Map existing specs to implementation work**: for each building block (password
   encryption, SMTP transport and send flow, connection endpoints, activate, test email,
   operator log, frontend wiring, test infrastructure), point to the governing permanent
   spec sections and name the classes/files expected (following
   `docs/agents/architecture.md` and existing code under `source/lib/`).
2. **List every open item** to be settled by the follow-up spec issues, one detail file per
   area, each owned by one follow-up issue:
   - `encryption.md` — password encryption (#151);
   - `transport.md` — SMTP transport behavior (#152);
   - `endpoints.md` — endpoint hardening and lifecycle (#153);
   - `testing.md` — test infrastructure (#154);
   - `frontend.md` — frontend alignment (#154).
   Each file starts with the open questions below that belong to it, marked
   **Pending decision**.
3. **Sketch the implementation sequence**: the expected implementation issues and their
   dependencies (defined later, after the spec issues land).

Open items to list (from epic #149):

- Pending in existing specs: encryption mechanism; `deactivate` endpoint; DB-level "at most
  one active" constraint; SMTP host deny-list / allow-list (SSRF); re-sending the password
  when `host`/`port`/`encryption` change; test-email rate limit; TLS certificate verification
  policy; `error.smtp.message` max length and fixed-message wording.
- Edge cases: key rotation; undecryptable stored password; `tls` without `STARTTLS`
  advertised; no supported AUTH mechanism; port/encryption mismatch; multi-line replies;
  SMTP timeouts vs PHP `max_execution_time`; IDN hosts and non-ASCII `fromAddress`; a
  connection edited/deleted during its test email; `PATCH` racing `activate`; frontend
  screens diverging from the real API.
- Security: AEAD with associated data binding the ciphertext to its row (working
  assumption: libsodium XChaCha20-Poly1305); no `AUTH` payload or password in any log; DNS
  rebinding; worker exhaustion from slow test emails; `error.smtp.message` rendered as text.

### Level of detail

For each building block, the spec states:

- **Responsibility** — one or two sentences: what it does and where it stops.
- **Proposed names** — class and namespace, following the existing layout (`Auth`, `Http`,
  `Infra`, `Model`, `Repository`); e.g. a new `Pidgey\Email\` namespace for transport and
  send flow, a home for password encryption, handlers under `Http\Handler\`. Names are
  **proposals**: `plan-issue` may rename them, leaving a note in the detail file.
- **Collaborators** — the existing classes it uses (`SmtpConnectionRepository`, `Gate`,
  `Validator`, `Transaction`, `Clock`, …).
- **Governing spec links** and the open items blocking it.

**Shared contracts** — one level deeper, because several implementation issues depend on
them: the transport interface (what a send takes and returns) and the stream abstraction the
fake SMTP server plugs into; the result / failure value types (SMTP reply code and message,
failure code) and their mapping to the `SMTP_*` HTTP errors; the password-encryption service
contract (inputs and outputs, including associated data); new configuration keys. For these,
list **what each carries** (fields and meaning), still without PHP signatures.

**Excluded** (left to `plan-issue`): method signatures, SQL, step-by-step algorithms,
per-test case lists, file-by-file change lists.

### Location and lifecycle

**Location:** `docs/agents/specs/implement-email.md` + `docs/agents/specs/implement-email/*.md`.
It lives under `specs/` (not `plans/` or `issues/`, which are per-issue) because it is
shared by every issue of #149, and agents read `specs/` first (AGENTS.md rule 1).

**Marked temporary:**

- Hub row in `docs/agents/specs.md`: `implement-email` — *Temporary: implementation spec of
  epic #149; removed when #149 closes.*
- Every file starts, right after its title, with:
  `> Temporary — part of epic #149. Permanent specs win on conflict; update them, not this
  file, when a decision changes the target behavior.`

**Lifecycle:**

1. **#150** creates the files with open items only.
2. **#151–#154** each settle their own detail file: decisions on *target behavior* go into the
   permanent specs; the detail file keeps only *implementation notes* (classes, order, test
   setup) linking to the permanent sections.
3. **Implementation issues** (phase 3) read it, and each ticks off its items in the matching
   detail file in the same PR.
4. **Cleanup issue** (last issue of #149): once every item is ticked, moves anything still
   useful into the permanent docs (`architecture.md`, `testing/`, …), deletes
   `implement-email*` and removes the hub row.
5. **Guard:** the cleanup issue's acceptance includes a grep proving no file under
   `docs/agents/` still links to `implement-email`.

This issue must write rules 2–5 into `implement-email.md` itself, so later issues follow them.

### Scope

#### In scope

- Create `docs/agents/specs/implement-email.md` and the five detail files above; index it in
  `docs/agents/specs.md`, marked temporary.
- Map every building block to the permanent spec sections that govern it.
- List every open item as a **Pending decision**, each in exactly one detail file.
- **Working assumptions** (e.g. AEAD XChaCha20-Poly1305, "always verify TLS") are recorded
  as *working assumption, to be confirmed in #15x* — never as decided.
- **Inconsistencies** found in the permanent specs while mapping them are listed as open
  items in the matching detail file, not fixed here.
- **New open items** discovered while writing are added to the matching detail file; one
  that fits none of #151–#154 goes under an **Unassigned** section of the index (no new
  issue is created).
- **Implementation sequence sketch**, non-binding: titles and dependencies only; the real
  implementation issues are created later by re-running `/arcanum-split-issue 149`.

#### Out of scope

- Deciding the open items (#151–#154 do that).
- Changing any permanent spec other than the hub index (`docs/agents/specs.md`).
- Comparing the frontend screens with the API (edge case 11): only listed in `frontend.md`;
  #154 performs it.
- Any code change.
- `POST /v1/messages` and password-recovery emails (later epics).

### Testing

None: spec files are not checked by tests or linters. Verification is PR review against
the acceptance list below.

## Benefits
- One entry point for every issue of #149; agents find it by reading `specs/` first.
- Each open item has exactly one owner issue (#151–#154), avoiding overlapping or forgotten decisions.
- Permanent specs stay the source of truth; the temporary spec is removed when the epic closes.
