# Product Owner Plan: Decide how pidgey-cli depends on pidgey-client

Main plan: [plan.md](plan.md)

## Overview

Write the decisions of issue #222 into `docs/agents/specs/node-client/` and its hub, and
remove every `**Pending decision** (#222)` marker. No code changes: `clients/` does not exist
yet. The staging-copy script and the lint are implemented by a later sub-issue of #219.

## Context

Decisions, from the issue:

- **Range.** A published `pidgey-cli` declares `"pidgey-client": "^x.y.z"`. The floor
  `x.y.z` is the library version on the branch being released, read from
  `clients/nodejs/package.json` and never bumped by hand.
- **`0.x` and `1.0.0`.**
  - On `0.x`, a library minor needs a CLI release that raises the floor.
  - From `1.0.0` the caret stays. A CLI release raises the floor when it uses a newer library
    feature, and a library major needs a CLI release.
- **Rejected options:** exact pin, tilde and lockstep versions, each with its reason.
- **Swap.**
  - The committed `clients/cli/package.json` keeps `file:../nodejs`.
  - A script in `clients/scripts/` (owner `infra`) copies `clients/cli/` to a temporary
    directory, rewrites the dependency to `^<floor>` and runs `npm pack` there, so the
    working tree is never modified.
  - The smoke test and the publish both use that script.
  - Rejected: a `prepack`/`postpack` rewrite in place, and a hand-edited floor.
- **Release order.**
  - Library first. A CLI-only release keeps the current library version as its floor.
  - Before a CLI publish, `npm view pidgey-client@<floor>` must succeed, or the publish
    aborts. This runs at publish time only.
- **Edge cases.**
  - A library patch that breaks the CLI is fixed forward with a library patch, and the bad
    version is deprecated through #223's rollback procedure.
  - No range exclusions and no emergency CLI pin.
  - No backports on `0.x`.
- **`--version`.**
  - It prints `pidgey-cli <v> (pidgey-client <v>)`.
  - The library exports a `VERSION` constant (a new export, not breaking).
  - A library spec checks that `VERSION` equals its `package.json` `version`.
- **Tests.**
  - The smoke test asserts that the packed CLI's `package.json` declares exactly `^<floor>`.
  - It installs both tarballs with `--offline`, since the CLI's range is satisfied by the
    packed library.
  - It asserts that `--version` prints both versions.
  - `checks.sh` fails if the committed CLI `package.json` declares `pidgey-client` as
    anything other than `file:../nodejs`.
- **Security.** The range admits only this project's own releases. A compromised library
  publish would reach library consumers anyway, so the publishing controls of #223 remain
  the defence.
- **Out of scope:** writing the script and lint, and everything #223 owns (mechanism, tags,
  credential, gate, rollback procedure).

## Implementation Steps

### Step 1 — Packaging spec and hub

In `docs/agents/specs/node-client/packaging.md`:

- **CLI to library dependency:** replace the `#222` pending decision with the decided rules,
  each with its rationale and the rejected alternatives:
  - the range;
  - the floor;
  - the committed `file:../nodejs`;
  - the staging-copy pack script;
  - library-first order and the registry check;
  - the `0.x` and `1.0.0` behaviour;
  - fix forward and no backports.

  State that the publish procedure of #223 must apply library-first order, the staging-copy
  pack and the registry check.
- **Versioning:**
  - replace "(subject to #222)" with a link to that section;
  - add the floor rule (a CLI release raises the floor when it needs a newer library);
  - add to the "What is breaking" table that raising the floor is **not** breaking for the
    CLI.
- **Supply chain:**
  - add the committed-file lint;
  - add the one-paragraph security rationale for a range compared with a pin.
- **Exports:** add `VERSION` to the list under the Packages table.

In `docs/agents/specs/node-client.md`, remove the `#222` row from the Pending decisions
table.

### Step 2 — Library, CLI and testing specs

- `docs/agents/specs/node-client/library.md`:
  - add `VERSION` to the Exports table: the library version string, equal to its
    `package.json` `version`;
  - mention the new export in the import example only if it fits naturally.
- `docs/agents/specs/node-client/cli.md`:
  - replace the `#222` pending item under "Thin layer" with one sentence and a link to
    [packaging.md](../../specs/node-client/packaging.md#cli-to-library-dependency);
  - change the `--version` row to print `pidgey-cli <version> (pidgey-client <version>)`,
    with the library version taken from `VERSION`;
  - state that `--version` prints the resolved library version, not the declared range.
- `docs/agents/specs/node-client/testing.md`:
  - **Library table:** add the `VERSION` equality check to an existing spec row, or a new
    `specs/Version_spec.js` row;
  - **CLI table:** the `Arguments_spec.js` `--version` case asserts both versions;
  - **Pack smoke test:**
    - replace the "CLI tarball" note with the staging-copy script;
    - install with `--offline`;
    - assert the packed CLI's `dependencies["pidgey-client"]` is exactly `^<floor>`;
    - `npx pidgey --version` prints both versions, the library one matching the packed
      tarball;
  - **`checks.sh` row and Cross-cutting:** add the committed-file lint (fails unless
    `clients/cli/package.json` declares `pidgey-client` as `file:../nodejs`) and the
    staging-copy pack.

Search the folder for any other `#222` mention (`grep -rn "#222" docs/agents/specs`) and
resolve it.

## Files to Change

- `docs/agents/specs/node-client/packaging.md`:
  - **CLI to library dependency:** the decided rules;
  - **Versioning:** the floor rule and the breaking table;
  - **Supply chain:** the lint and the security rationale.
- `docs/agents/specs/node-client.md`: remove the `#222` pending row.
- `docs/agents/specs/node-client/library.md`: the `VERSION` export.
- `docs/agents/specs/node-client/cli.md`: the pending item becomes a link; the `--version`
  output.
- `docs/agents/specs/node-client/testing.md`:
  - the `VERSION` spec and the `--version` assertion;
  - the smoke-test staging copy, `--offline` install and range assertion;
  - the committed-file lint in `checks.sh`.

## CI Checks

- No CI job validates these Markdown files. Keep the existing relative links valid and
  check them by reading.

## Notes

- Write in the specs' existing style: short bullets, decided rules first, then a
  **Rejected:** list.
- Do not touch the `#223` and `#226` markers. #222 only states what the #223 procedure has
  to do for the dependency.
- `docs/agents/issues/222-…md` is the source of these decisions; do not edit it.
