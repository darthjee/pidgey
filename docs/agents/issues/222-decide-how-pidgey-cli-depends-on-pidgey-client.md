# Issue: Decide how pidgey-cli depends on pidgey-client

## Description

Decide and specify how the published `pidgey-cli` (`clients/cli/`, command `pidgey`) depends on the published `pidgey-client` library (`clients/nodejs/`), how the in-repository `file:../nodejs` dependency becomes that published range at pack time, and how the rule evolves once the library reaches `1.0.0`. Spec-only enhancement step of epic #219; the decisions go into `docs/agents/specs/node-client/`.

## Problem

Epic #219 ships two independently versioned npm packages. During development the CLI depends on the library by path (`file:../nodejs`), but a published CLI must depend on a published `pidgey-client`, and how tight that dependency is has not been decided. Both packages start at `0.1.0`, and under semver a caret range on `0.x` (`^0.1.0`) only accepts patch releases (`>=0.1.0 <0.2.0`), so the choice interacts with the `0.x` phase.

The specs carry three pending items for #222: [packaging.md](../specs/node-client/packaging.md#cli-to-library-dependency) (and its "subject to #222" note under Versioning), [cli.md](../specs/node-client/cli.md#thin-layer), and the hub table in [node-client.md](../specs/node-client.md). The pack smoke test in [testing.md](../specs/node-client/testing.md#pack-smoke-test-adopted) also needs the CLI tarball "with the dependency in its published form, which #222 defines".

### Options considered

| Option | Effect | Trade-off |
| --- | --- | --- |
| Caret range (`^0.1.0`) | CLI users get library patch fixes without a CLI release; on `0.x` a library minor still needs a CLI release. | Installs are not byte-for-byte reproducible across time; relies on the library honouring semver. |
| Tilde range (`~0.1.0`) | Same as caret while on `0.x`; stricter after `1.0`. | Same as above. |
| Exact pin (`0.1.0`) | Every library change needs a CLI release; fully reproducible installs. | Release overhead; CLI lags library fixes. |
| Lockstep versions | Both packages always released together with the same version. | Couples two otherwise independent release cycles. |

## Expected Behavior

The spec files record the decisions below and no `#222` pending item remains:

- `docs/agents/specs/node-client/packaging.md`:
  - **CLI to library dependency:** the caret range, the floor rule, the staging-copy pack script, library-first release order, the registry check, and the `1.0.0` behaviour;
  - **Versioning:** the "subject to #222" note is replaced by the floor rule;
  - **Supply chain:** the committed-file lint, and the security rationale of the range.
- `docs/agents/specs/node-client/cli.md`: the pending item is replaced by a link to packaging.md, and the `--version` row prints both versions.
- `docs/agents/specs/node-client/library.md`: the new `VERSION` export.
- `docs/agents/specs/node-client/testing.md`:
  - the pack smoke test's CLI tarball note points at the staging-copy script;
  - the smoke test gains the dependency-range assertion and the offline install, and its `--version` assertion checks both versions;
  - the library spec for `VERSION` and the committed-file lint are listed.
- `docs/agents/specs/node-client.md`: the `#222` row is removed from the pending-decisions table.

Out of scope:

- Writing the staging-copy script and the lint: a later implementation sub-issue of #219, since `clients/` does not exist yet.
- Everything #223 owns: the publishing mechanism, tags, the credential, the pre-publish gate and the rollback procedure. #222 only defines what that procedure must do for the dependency: library first, pack through the staging copy, the registry check.

## Solution

### Range: caret with a floor

- A published `pidgey-cli` declares `"pidgey-client": "^x.y.z"`. The **floor** `x.y.z` is the library version the CLI release was built and tested against.
- **Why:** the CLI is a thin layer; all HTTP and retry logic lives in the library. Library patch fixes therefore reach CLI users without a CLI release. The library has zero runtime dependencies and only this project publishes it, so the range pulls in nothing but our own releases.
- **On `0.x`:** `^0.1.0` means `>=0.1.0 <0.2.0`. A library minor, which is breaking on `0.x`, is not accepted; it needs a CLI release that raises the floor.
- **From `1.0.0`:**
  - The caret stays: `^1.y.z` also accepts library minors, which are additive.
  - A CLI release raises the floor when it starts using a newer library feature.
  - A library major needs a CLI release.
- **Rejected:**
  - **Exact pin:** every library fix would need a CLI release. npm ignores the CLI's lockfile for consumers, so the pin buys little reproducibility.
  - **Tilde:** identical to caret on `0.x`; after `1.0` it would force a CLI release for every additive library minor.
  - **Lockstep versions:** couples two independent release cycles and forces empty CLI releases.

### Swap at release time: staging-copy pack script

- The committed `clients/cli/package.json` keeps `file:../nodejs`. A committed `^0.1.0` could not install before `0.1.0` is on npm, and it would need yarn link or resolutions in dev and CI.
- A script in `clients/scripts/`, owned by `infra`:
  1. copies `clients/cli/` into a temporary directory;
  2. rewrites `dependencies["pidgey-client"]` to `^<version>`, with the version read from `clients/nodejs/package.json`;
  3. runs `npm pack` there.

  The working tree is never modified. The pack smoke test and the publish step use this same script, so CI tests the exact tarball shape that gets published.
- **The floor is derived, never bumped by hand.** It is always the library version on the branch being released.
- **Release order: library first.**
  - A release that changes both packages publishes `pidgey-client` first, then `pidgey-cli`.
  - A CLI-only release keeps the current library version as its floor.
- **Registry check before a CLI publish:** the publish confirms with `npm view` that `pidgey-client@<floor>` exists on npm, and aborts otherwise. This catches a library version that was bumped in the repository but never published. The check runs only at publish time.
- **Rejected:**
  - A `prepack`/`postpack` hook rewriting the committed file in place: a failed pack leaves it dirty.
  - A hand-edited floor field: one more manual step that can drift from the real library version.

### Edge cases

- **A library patch breaks the CLI.** Every CLI install accepts it through the caret, so the fix goes forward in the library:
  - a library patch release reverts or fixes the change, and caret installs pick it up;
  - the bad version is deprecated through #223's rollback procedure;
  - no range exclusions in the CLI (for example `^0.1.0 !0.1.3`), and no emergency CLI pin.
- **No backports on `0.x`:** a CLI still on `^0.1.x` gets no fixes made in `0.2.x`. Users upgrade the CLI.
- **`pidgey --version` prints both versions**, for example `pidgey-cli 0.1.0 (pidgey-client 0.1.3)`. Under a range, the resolved library version is what a bug report needs.
  - `pidgey-client` exports a `VERSION` constant, a new export and so not breaking.
  - A library spec asserts that `VERSION` equals the `version` in its `package.json`, since there is no build step to inject it.
- **Smoke test before the first publish:** the smoke test installs the packed library tarball together with the packed CLI tarball. The CLI's `^<floor>` is satisfied by that library tarball, so the install runs with `--offline` and never needs `pidgey-client` on the registry.

### Testing, performance and security

- **Smoke-test assertions:**
  - the `package.json` inside the packed CLI tarball declares `dependencies["pidgey-client"]` as exactly `^<version in clients/nodejs/package.json>`;
  - `npx pidgey --version` prints both the CLI version and the packed library's version.
- **Committed-file lint:** `clients/scripts/checks.sh` fails if the committed `clients/cli/package.json` declares `pidgey-client` as anything other than `file:../nodejs`.
- **Security:**
  - The range admits only this project's own releases, published under the token, 2FA and provenance rules of [packaging.md](../specs/node-client/packaging.md#supply-chain) and #223.
  - The one added risk: a compromised `pidgey-client` publish reaches CLI installs without a CLI release. A pin would not protect library consumers from the same publish, so the publishing controls remain the defence.
- **Performance:** no impact, since there is one extra package and no transitive dependencies either way.

## Benefits

- CLI users get library fixes without waiting for a CLI release.
- Releases need no manual version edits: the floor is derived, and the working tree is never rewritten.
- CI packs and tests the same tarball shape that gets published.
- Three pending decisions in the Node client specs are closed.
