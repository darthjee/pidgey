# Plan: Release pidgey-client and pidgey-cli 0.1.0

Issue: [254-release-pidgey-client-and-pidgey-cli-0-1-0.md](../../issues/254-release-pidgey-client-and-pidgey-cli-0-1-0.md)

## Overview

The release PR makes `0.1.0` publishable and documents the one-off manual first release:

- a `## 0.1.0` entry in both `CHANGELOG.md` files, which `release_pack.sh` requires;
- doc updates recording that `0.1.0` is published by hand through the repo's release scripts, with no `0.0.0` placeholders and no token setup, and that CI publishing starts after #266.

Both `package.json` files already say `0.1.0`, and the CLI's floor is derived at pack time, so nothing else in `clients/` changes. The publish itself and the post-release `known-gaps.md` update happen after the merge and are not part of this PR.

## Agents involved

- [nodejs](nodejs.md)
- [cli](cli.md)
- [product-owner](product-owner.md)

## Shared contracts

- **CHANGELOG heading:** a bare `## 0.1.0` line, matching the gate regex `^## 0\.1\.0( |$)` in `clients/scripts/release_pack.sh`. No date, no brackets. It sits below `## Unreleased`, which stays and stays empty.
- **Versions:** `clients/nodejs/package.json` and `clients/cli/package.json` stay at `0.1.0`. The committed CLI dependency stays `"pidgey-client": "file:../nodejs"`; the published range `^0.1.0` comes from the staging-copy pack script.
- **Manual release runbook** (written by `product-owner` in `operations.md`), per package, library first, from a clean checkout of the merge commit:
  1. `out="$(mktemp -d)"`, then `clients/scripts/release_pack.sh pidgey-<pkg>-v0.1.0 "$out"`;
  2. `clients/scripts/release_publish.sh --dry-run pidgey-<pkg>-v0.1.0 "$out/pidgey-<pkg>-0.1.0.tgz"`;
  3. `npm publish "$out/pidgey-<pkg>-0.1.0.tgz" --access public`, with 2FA;
  4. checks with `npm view`;
  5. no tag push until #266 lands.
