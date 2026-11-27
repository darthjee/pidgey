# Plan: Build the npm publish pipeline of the clients

Issue: [253-build-the-npm-publish-pipeline-of-the-clients.md](../../issues/253-build-the-npm-publish-pipeline-of-the-clients.md)

## Overview

Add two release scripts under `clients/scripts/` (pack + gate, and publish with a dry-run mode) and four CircleCI jobs (`pack_client`, `publish_client`, `pack_cli`, `publish_cli`) that run only on the per-package tags `pidgey-client-vX.Y.Z` / `pidgey-cli-vX.Y.Z`. Each package gets an empty `CHANGELOG.md` skeleton that the pack gate reads. `docs/agents/deployment/` and the packaging spec record the jobs, the `pidgey-npm` context, the release and rotation procedures, and the provenance outcome. The first real publish is #254. This issue proves the path with a dry run.

## Agents involved

- [nodejs](nodejs.md): `clients/nodejs/CHANGELOG.md` skeleton
- [cli](cli.md): `clients/cli/CHANGELOG.md` skeleton
- [infra](infra.md): release scripts, CircleCI jobs, provenance research, deployment docs (the docs under `docs/agents/deployment/` are `architect`'s, who reviews that step)

## Shared contracts

- **`CHANGELOG.md` shape** (written by `nodejs`/`cli`, read by the infra pack gate):
  - first line `# Changelog`;
  - one `## Unreleased` section in the skeleton;
  - a release entry is a level-2 heading starting with the bare version: `## 0.1.0` (optionally followed by ` - YYYY-MM-DD`). The gate matches `^## <version>( |$)` (regex-escaped dots).
  - `CHANGELOG.md` is **not** added to the `files` allowlist, so it does not ship and `pack-files.txt` stays unchanged.
- **Tags**: `pidgey-client-v<semver>` → package dir `clients/nodejs`, npm name `pidgey-client`; `pidgey-cli-v<semver>` → `clients/cli`, `pidgey-cli`.
- **Workspace layout**: pack jobs persist `release/<tarball>.tgz` (exactly one tarball per pack job) at workspace root; publish jobs attach at `/tmp/workspace`.
- **Context**: `pidgey-npm`, holding only `NPM_TOKEN`; attached only to `publish_client` / `publish_cli`.
