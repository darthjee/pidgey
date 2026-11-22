# Issue: Reserve the npm names pidgey-client and pidgey-cli

## Description

Reserve the npm names `pidgey-client` and `pidgey-cli` by publishing a `0.0.0` placeholder of each, by hand, before epic #219 reaches its first real release (`0.1.0`).

## Problem

Epic #219 publishes two packages to the public npm registry: the library `pidgey-client` (`clients/nodejs/`) and the command-line client `pidgey-cli` (`clients/cli/`, global command `pidgey`). npm names are first-come, first-served, so either name can be taken before the epic reaches its publish step.

- As of 2026-11-21, `npm view pidgey-client` and `npm view pidgey-cli` both answer `404`: the names are still free and **not reserved yet**.
- `docs/agents/specs/node-client/packaging.md` already states the names "are reserved with manual `0.0.0` placeholders (#227)", so the spec gets ahead of reality until this is done.
- The unscoped `pidgey` belongs to an unrelated package (an event emitter), which is why the CLI's package name differs from its command.
- #223 (merged) decided the publishing settings: 2FA required on the maintainer's npm account and in both packages' publishing settings, allowing only 2FA or a granular token. The granular CI token is limited to these two packages, so they must exist before it can be created.

## Expected Behavior

- `npm view pidgey-client` and `npm view pidgey-cli` show version `0.0.0`, with the maintainer as the only maintainer.
- Neither placeholder has code or a `bin` entry: installing `pidgey-cli@0.0.0` does not create a `pidgey` command.
- Both packages' publishing settings require 2FA and allow only 2FA or a granular token, as set in #223, from the moment they are reserved.
- Nothing is committed to this repository for the placeholders themselves: they are built and published by hand, outside the repository.
- `packaging.md`'s Name reservation bullet records the two rules decided here: publishing settings locked right after reserving, and `0.0.0` deprecated after `0.1.0`.
- After `0.1.0` is published, `0.0.0` of each package is deprecated.

## Solution

A manual task for the maintainer, outside CI (no publish pipeline exists yet) and outside this repository.

### 1. Prerequisite

- 2FA is enabled on the maintainer's npm account.
- Re-check that both names are still free (`npm view <name>` answers `404`).

### 2. Placeholder contents, per package

Built in a temporary directory, never committed:

- `package.json`:
  - `name`: `pidgey-client` or `pidgey-cli`;
  - `version`: `0.0.0`;
  - `description`: reserved for the upcoming Pidgey client;
  - `repository`: this GitHub repository;
  - `license`: `MIT`, the repository's license;
  - no `main`, no `bin`, no dependencies, no scripts.
- `README.md`: says the name is reserved for the upcoming Pidgey client and links to this repository.

### 3. Publish

From each temporary directory: `npm publish --access public`, confirmed with 2FA.

### 4. Lock down publishing

In each package's npm settings, set publishing access to require 2FA, allowing only 2FA or a granular token, as decided in #223.

### 5. Verify

- `npm view pidgey-client` and `npm view pidgey-cli` show `0.0.0` and the maintainer.
- Do this before the spec PR merges, since merging it closes #227.

### Later, after `0.1.0`

Once the first real release of a package is on npm, the maintainer deprecates its placeholder from their machine with 2FA, never from CI, as in #223's rollback procedure:

```bash
npm deprecate pidgey-client@0.0.0 "placeholder; use >=0.1.0"
npm deprecate pidgey-cli@0.0.0 "placeholder; use >=0.1.0"
```

### Spec change (`product-owner`)

The only file change of this issue: in `docs/agents/specs/node-client/packaging.md`, the **Name reservation** bullet gains:

- the publishing settings (2FA required, only 2FA or a granular token allowed) are applied to each package right after its `0.0.0` is published;
- after a package's first real release (`0.1.0`), the maintainer deprecates its `0.0.0` from their machine with 2FA, never from CI, with the message `placeholder; use >=0.1.0`.

The npm steps above stay manual for the maintainer; no agent runs them.

## Benefits

- The two names are secured before anyone else can take them.
- The packages exist, so the granular CI token decided in #223 can be limited to them.
- 2FA protects both names from the start, not only once CI publishing exists.
- `packaging.md`'s statement that the names are reserved becomes true.
