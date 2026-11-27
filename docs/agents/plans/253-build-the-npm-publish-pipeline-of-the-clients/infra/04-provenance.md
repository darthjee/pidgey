# Provenance research and decision

Check current npm documentation for:

1. whether `npm publish --provenance` can generate an attestation from CircleCI (historically only GitHub Actions and GitLab CI were supported providers);
2. whether npm **trusted publishing** (OIDC) lists CircleCI as a provider;
3. the current granular-token limits: maximum expiry for write tokens, the exact setting names, and how a granular token interacts with packages set to "require 2FA" (the bypass a CI token needs).

Decide:

- `--provenance` supported from CircleCI → enable it in `release_publish.sh`.
- Not supported → keep it off, and record the gap: a new section in `docs/agents/deployment/known-gaps.md` ("npm packages published without provenance"), with what is missing, why, and what would close it. Replace the "To confirm" bullet in the Provenance section of `docs/agents/specs/node-client/packaging.md` with the outcome.
- Trusted publishing supports CircleCI → **do not** switch in this issue. Keep `NPM_TOKEN`, and open a follow-up GitHub issue to move to OIDC, linked from the known-gap or spec note.

Record the token findings (cap, setting names, 2FA bypass) in place of the token "To confirm" bullet in the spec, and in `variables.md` / `operations.md` (step 05). Cite the npm doc URLs.

## Files to Change
- `clients/scripts/release_publish.sh` — enable `--provenance` only if supported.
- `docs/agents/specs/node-client/packaging.md` — resolve both "To confirm" bullets.
- `docs/agents/deployment/known-gaps.md` — provenance gap, if any.
