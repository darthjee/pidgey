# Issue: Deployment implementation docs

## Description

Part of epic #95. Document the deployment **as actually implemented** (release `0.1.0`, deploy script `darthjee/scripts:0.11.0`), so the docs can replace the deployment specs as the reference. #102 removes the specs afterwards.

Owner: infra. The architect reviews the result.

## Expected Behavior

- `docs/agents/deployment.md` is a short index linking to topic files under `docs/agents/deployment/`.
- The docs describe what `.circleci/config.yml`, the CI images and the host actually do, not the intended design.
- No real host name, SSH user, home path, folder path or domain appears anywhere. The docs refer to them only through CircleCI variable names or placeholders such as `<live>`.

## Solution

### Topic files (proposed split)

- **Pipeline:** the real flow, the CircleCI jobs and their order, the tag filter, the `pidgey-deploy` context and its expression restriction, the `serial-group`, the `SSH_KNOWN_HOSTS` guard, the `deploy_frontend.sh` actions used and the script version (`0.11.0`, no `cleanup`).
- **Variables:** every CircleCI variable in the context and what it holds, including the value formats found in #100:
  - `SSH_PRIVATE_KEY` is stored as one line, with each newline escaped as a literal `\n`;
  - `SSH_KNOWN_HOSTS` holds a single ED25519 line.
- **Server:**
  - the live, staging and shared folders;
  - the `config/.env` link;
  - the web directory pointing at the live release's `public/`;
  - the shared `.env` keys and permissions.
- **Operations:**
  - releasing (pushing a semver tag);
  - rerunning a failed workflow;
  - rotating the deploy key or `SECRET_KEY`;
  - the manual recovery when the swap breaks (`mv <live>_old_<ts> <live>`);
  - removing stale staging folders by hand, since `0.11.0` has no `cleanup`.
- **Known gaps:**
  - no proxy rate limiting on the host. Production keeps `USER_APPROVAL_REQUIRED=true`, and app-level throttling stays an open decision in `specs/api/pending.md`, which owns it. The docs link to it instead of adding a second pending entry;
  - one DB user for both migrations and the app. The host supports a second user, and the split is tracked in #109;
  - no HSTS header yet (tracked in #110);
  - `TRUSTED_PROXY_HOPS=0` can't be checked from outside until an endpoint shows the client IP;
  - every path returns `200 {"status":"ok"}` for now, because the status handler is the only handler. The router with a 404 fallback is tracked in #111.
- The `/auth/*.json` per-IP proxy rate-limit recommendation from `specs/deployment/production.md`.

### Related docs

- `docs/agents/contributing.md`: drop the *(planned)* markers from `frontend_checks` and the deploy jobs (`build_backend`, `build_frontend`, `upload_backend`, `upload_frontend`, `release`), which exist now. Keep it on `build_images`, which is still planned. Point the deploy jobs' row at the new deployment docs.
- `AGENTS.md`: add a "Deployment" row to the documentation table, linking `docs/agents/deployment.md`.

### Out of scope

- Deleting the deployment specs and repointing the links to them (#102). #102 also moves the non-deployment pending decisions (frontend coverage on Codacy, automated `build_images`) to the spec that owns them.
- Any change to CI config, images or code.
