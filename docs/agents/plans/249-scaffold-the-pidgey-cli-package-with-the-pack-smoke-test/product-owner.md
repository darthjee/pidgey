# Product-owner Plan: Scaffold the pidgey-cli package with the pack smoke test

Main plan: [plan.md](plan.md)

## Shared contracts

What this agent documents (produced by `cli` and `infra`):

- `clients/cli/` (`pidgey-cli`, version `0.1.0`, command `pidgey` from `bin/pidgey.js`,
  sources under `cli/`, specs under `specs/`), committed dependency `file:../nodejs`,
  scripts `lint`, `typecheck`, `test`, `coverage` at 100 %, `pack-files.txt`.
- Behaviour today: `--version` (`pidgey-cli <version> (pidgey-client <version>)`), `--help`;
  anything else is a usage error, exit `2`. The send flags come with #251.
- `clients/scripts/pack_cli.sh <out_dir>` (staging-copy pack, reused by #253).
- `checks.sh`: packages `nodejs` then `cli`; the fresh library copy for the CLI; the
  committed-file lint; the pack smoke test after the pack file-list checks.
- `clients_checks`: CLI Codacy upload (prefix `clients/cli`, current version only) and the
  clean-working-tree step. Compose volume `clients_cli_node_modules`.

## Implementation Steps

### Step 1 — As-built docs

Write them from the merged code, not from this plan, where they differ:

- `docs/agents/clients/cli.md` (new), shaped like `clients/nodejs.md`: Package, Surface
  (`--version`, `--help`, usage error), Specs layout, Scripts, Pack check (its own
  `pack-files.txt`), the dependency on the library (`file:../nodejs` committed, `^<floor>` in
  the packed tarball through `pack_cli.sh`, the committed-file lint), Commands
  (`docker compose run --rm clients sh -c "cd clients/cli && yarn <script>"`), CI.
- `docs/agents/clients.md`: index `cli.md` in the file table; `clients/cli/` row → built
  (`--version`, `--help`; send flags in #251); `clients/scripts/` row → also `pack_cli.sh`
  (and `smoke_test.sh` if it exists); Checks section: the package list `nodejs cli`, the
  committed-file lint, the smoke test and what it asserts; `cli` in the Owners table; drop
  #249 from "Not built yet". Update the library row's retry note if it is stale (#248 merged).
- `docs/agents/clients/nodejs.md`: the pack smoke test (the library tarball installed
  offline with the CLI; `import` through `exports` and `require(esm)` asserted); that a
  library `version` bump must also refresh `clients/cli/yarn.lock` if the frozen install
  then fails (check what Yarn does and record the real rule); the CI section's second
  upload.
- `docs/agents/deployment/pipeline.md`: `clients_checks` row and the Codacy paragraph — the
  `clients/cli` upload and the clean-working-tree step; the smoke test in the job's list.
- `docs/agents/docker/compose.md`: the `clients` service mounts a `node_modules` volume per
  package (`nodejs`, `cli`); link `clients/cli.md` too.
- `docs/agents/contributing/testing.md`: the clients paragraph covers `clients/cli/` and its
  one-script command.

### Step 2 — #243 hand-off (files owned by `architect`, for review)

- `.claude/agents/cli.md`: replace the *(planned)* line under Commands with the real
  commands, as in `.claude/agents/nodejs.md`: `make clients-checks`,
  `docker compose run --rm clients clients/scripts/checks.sh`,
  `docker compose run --rm clients sh -c "cd clients/cli && yarn lint"` (or typecheck,
  test, coverage); and in Conventions drop "(planned: #249)" after `docs/agents/clients/cli.md`.
- `docs/agents/folder-structure.md` and `docs/agents/architecture/repository-layout.md`:
  drop *(planned), coming in #249* from `cli/` in the `clients/` row, and mention
  `pack_cli.sh` next to `checks.sh` under `scripts/`.

## Files to Change

- `docs/agents/clients/cli.md` — new.
- `docs/agents/clients.md` — index, rows, Checks, Owners, Not built yet.
- `docs/agents/clients/nodejs.md` — smoke test, version bump rule, CI.
- `docs/agents/deployment/pipeline.md` — `clients_checks`.
- `docs/agents/docker/compose.md` — `clients` service volumes.
- `docs/agents/contributing/testing.md` — clients paragraph.
- `.claude/agents/cli.md` — commands (architect-owned, flagged for review).
- `docs/agents/folder-structure.md`, `docs/agents/architecture/repository-layout.md` — drop the planned marker.

## Notes

- `docs/agents/specs/node-client/*.md` stay as they are: they already specify everything
  built here, and the spec is removed only once the as-built docs cover all of it.
