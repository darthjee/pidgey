# Dry-run demonstration

Before #254 does the first real publish, show the path working without publishing anything:

1. The gates fail as expected on the skeleton CHANGELOGs: `release_pack.sh pidgey-client-v0.1.0 <tmp>` exits non-zero with the "no CHANGELOG entry for 0.1.0" message. Also check that a mismatched tag such as `pidgey-client-v9.9.9` fails the version gate, and that an unrelated tag fails the tag parse.
2. With a temporary local `## 0.1.0` line added to both CHANGELOGs (not committed), run for each package `release_pack.sh <tag> <tmp>`, then `release_publish.sh --dry-run <tarball>`. Expected: the pack check passes, the no-op check reports "not published", the CLI floor check warns that `pidgey-client@0.1.0` is missing, and `npm publish --dry-run` lists the same files as `pack-files.txt`. Then revert the CHANGELOG edits.
3. Run the no-op path: `release_publish.sh --dry-run` on a tarball whose version is already on npm (for example, repack the library at `0.0.0` in a temp copy) exits 0 with the "already published" message.
4. Paste the outputs into the PR description, together with the maintainer checklist (token, context and expression restriction, 2FA).

No committed files change in this step.

## Files to Change
- None (verification only; results go in the PR description).
