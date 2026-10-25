# Issue: Frontend setup specs: dev environment, release and naming

## Description

Part of #15. Goes first: sub-issues 2–4 implement what these specs describe.

The frontend was copied from citadel into `frontend/` (`57cd38d Init frontend (#14)`), but the specs
don't yet describe how it is set up for development and release. Specs are the source of truth
(AGENTS.md rule 2), so document the setup before implementing it.

Update the index `docs/agents/specs/frontend.md` and the files under `docs/agents/specs/frontend/`
(one new file, see [File split](#file-split)), plus the related specs.

## Problem

The specs still describe a frontend setup that doesn't match the copied code or the agreed design:
`build.md` mounts `dist/` as `static/`, `testing.md`/`layout.md` name `eslint.config.js` and
`specs/client/…_spec.js`, `docker/*.md` and `deployment/pipeline.md` mark `frontend_dev`,
the frontend volumes and `frontend_checks` as future work, and nothing documents the Node image,
the Vite proxy, the first-run steps or the release target. The implementation sub-issues of #15
(image/compose/Makefile, CI, agent) need these specs as their source of truth.

## Expected Behavior

After this issue, `docs/agents/specs/` (plus `docs/agents/folder-structure.md` and `README.md`)
fully and consistently describe the frontend dev environment, release and naming: a developer can
follow `frontend/development.md`'s First run to reach `http://localhost:8030/admin/`, every shared
name (`frontend_dev`, `vite_pidgey-base`/`vite_pidgey`, `FRONTEND_PORT`, `FE_BASE_VERSION`,
`source/public/admin`, `eslint.config.mjs`, `specs/assets/js/**/*Spec.js`) is defined once, and
the remaining open questions are recorded as Pending decisions. Docs only — no code changes.

## Solution

### File split

One new file; the rest goes into the existing files.

| File | Content |
| --- | --- |
| **`frontend/development.md`** (new) | Dev workflow: `vite_pidgey-base`/`vite_pidgey` images and the `frontend_dev` service (brief, linking to `docker/images.md` / `docker/compose.md`); the Vite `/v1` proxy to `app` and why; the session-cookie security note; **First run** (`make build-fe-base build-fe` → `make fe-install` → `make dev-up` → `http://localhost:8030/admin/`); dependencies live in `docker_volumes/node_modules` (the bind mount hides the image's install). |
| `frontend/build.md` | Becomes **build and release** (name kept, so links stay valid): `yarn build`, Vite `base: '/admin/'`, `make build-fe-release` → `source/public/admin` served at `/admin/`, the merged production pending decision (A/B/C) with the interim git-ignore rule, the pending `/admin` rewrite question. |
| `frontend/testing.md` | `eslint.config.mjs`, `specs/assets/js/**/*Spec.js` (glob `specs/**/*[sS]pec.js`), the real scripts (`test` runs Jasmine under **nyc**, `coverage` under **c8** with lcov, `lint`/`lint_fix` on `assets specs`), and how checks run (`make tests` locally, `frontend_checks` in CI, linking to `deployment/pipeline.md`). |
| `frontend/layout.md` | See [layout.md changes](#layoutmd-changes). |
| `frontend.md` (index) | Add the `development.md` row; update the `build.md` description. |

#### layout.md changes

The folder tree is updated to the copied code (citadel's conventions, which the frontend agent
also follows):

```
frontend/
  index.html
  vite.config.js
  package.json
  yarn.lock
  eslint.config.mjs
  assets/js/
    main.jsx
    App.jsx
    client/                 # domain API clients
    components/
      AppController.js
      common/ helpers/ resources/
    utils/
      polling/ routing/ validation/
  specs/
    assets/js/              # Jasmine specs mirroring assets/js/, named *Spec.js
      client/ components/ utils/
    support/                # shared test helpers (jsx-loader.mjs, fakeWindow, fetchSequence, …)
```

- The **target** client list (`ApiClient`, `SessionClient`, `OrganizationsClient`,
  `SmtpConnectionsClient`, `ApiTokensClient`) and the client-layer rules stay as they are.
- Add a short **Current state** note: `client/` still holds citadel's clients (`ApiClient` with
  JWT refresh via `/auth/refresh.json`, `AccountsClient`, `AdminClient`, `AuthSession`,
  `AuthStatus`, `AuthEvents`, …), to be replaced by the target clients in follow-up issues; new
  code must not build on citadel's `/auth/*` and `/users*` routes.

**No duplication:** `docker/*.md` and `deployment/pipeline.md` remain the source of truth for
image, service, Makefile-target and CI-job definitions; the frontend specs describe the workflow
and link to them (no copied YAML/Makefile snippets). `README.md` links to `development.md`'s
First run section.

Alternative rejected: a single `frontend/setup.md` — it would overlap the existing `build.md`
and `testing.md`.

### Decisions to record

- **Node image:** a separate `vite_pidgey-base` image (built from `dockerfiles/vite_pidgey-base/Dockerfile`,
  repo root as build context, based on `darthjee/node`, yarn cache pre-warmed with
  `darthjee/scripts` `yarn_builder.sh`), with `vite_pidgey` on top. Tagged with the
  `<version>` / `<version>-arm64` `BASE_SUFFIX` convention. Resolves the "Node.js/Yarn" pending
  decision in `docker/images.md`.
- **Dev:** `frontend_dev` compose service (image `vite_pidgey`, port `${FRONTEND_PORT:-8030}:8080`,
  `./frontend` and `./docker_volumes/node_modules` mounted, `depends_on: app`, `yarn server`).
  Vite proxies `/v1` to `http://app:80` (`changeOrigin: false`). No Tent.
- **Release:** `make build-fe-release` runs `yarn build` and copies `frontend/dist/` into
  `source/public/admin`; Vite `base: '/admin/'`, used in **both** dev and build (dev URL
  `http://localhost:8030/admin/`), so dev matches production and asset-path bugs show up early.
  The UI is served at `/admin/`.
- **`static/` → `admin/`:** the built frontend no longer lives under `static/` anywhere in the
  specs; it is `source/public/admin`, served at `/admin/`. Since `app` already mounts `./source`,
  a release build is served by Apache on :8080 in development with no extra volume.
- **Naming:** keep citadel's layout — `eslint.config.mjs`, specs under `frontend/specs/assets/js/…`
  named `*Spec.js`. Update the specs (currently `eslint.config.js`, `specs/client/…`, `*_spec.js`).
- **Makefile:** `build-fe-base`, `push-fe-base`, `build-fe`, `fe-install`, `build-fe-release`;
  `dev-up` adds `frontend_dev`; `tests` appends frontend `yarn lint && yarn coverage`.
- **CI:** `frontend_checks` job (lint + coverage) on `darthjee/circleci_node`; no longer future work.
- `package.json` scripts include `lint_fix`.

### Files

| File | Change |
| --- | --- |
| `specs/frontend.md` + `specs/frontend/*.md` | As in [File split](#file-split). `build.md` drops the `dist` → `static/` mount; release = `make build-fe-release` → `source/public/admin`, served at `/admin/` by `app` on :8080 through the existing `./source` mount. |
| `specs/docker/images.md` | Rows for `vite_pidgey-base` / `vite_pidgey`; resolve the Node.js/Yarn pending decision. |
| `specs/docker/compose.md` | `frontend_dev`: drop "Future work", image `vite_pidgey`, add `FRONTEND_PORT`. |
| `specs/docker/volumes.md` | `./frontend` and `./docker_volumes/node_modules`: drop "Future work"; **delete** the `./frontend/dist → <web root>/static` row. |
| `specs/docker/images.md` (production `pidgey` row) | Built frontend `dist/` goes in the web root's `admin/`. |
| `specs/architecture/backend.md` | Built frontend under `admin/`, served at `/admin/` directly by Apache; the front controller only gets requests that are not existing files or directories; `/admin/` is reserved for the UI. |
| `specs/overview/components.md` | Admin UI served at `/admin/`. |
| `specs/docker/makefile.md` | New targets, `dev-up`/`tests` changes, first-run steps. |
| `specs/deployment/pipeline.md` | `frontend_checks` no longer future work; `upload_fe_files` (still future work) uploads `dist/` to the web root's `admin/`. |
| `specs/architecture/repository-layout.md`, `docs/agents/folder-structure.md` | Drop "(planned)" from `frontend/` and `node_modules/`; `source/public/`: built frontend assets go under `admin/` (not `static/`). |
| `README.md` | Link to the First run section of `frontend/development.md`. |

### Pending decisions to record

- Whether Apache serves `/admin/index.html` directly or via an `.htaccess` rewrite (hash routing
  needs no route fallback). Either way the rewrite to `index.php` must keep skipping existing
  files, so `/admin/assets/*` never reach PHP; open point is how `/admin` (no trailing slash) and
  `/admin/` resolve to `index.html`.
- **How the built frontend reaches production** — one decision, replacing both `build.md`'s
  current "bake `dist/` into the image vs. upload it separately" and "commit vs. git-ignore
  `source/public/admin`". Recorded once in `frontend/build.md`; `docker/images.md` (production
  row) and `deployment/pipeline.md` (`upload_fe_files`) link to it instead of restating it.
  Options:
  - **A. Committed** — `source/public/admin` is committed; the prod image (built from `source/`)
    picks it up; no upload job. Simplest, but build output in git (noisy diffs, stale bundle risk).
  - **B. Git-ignored, built in the prod image** — multi-stage prod Dockerfile (Node stage runs
    `yarn build`, copies `dist/` into `admin/`). Clean git, always in sync; prod image needs the
    repo root as build context.
  - **C. Git-ignored, uploaded** — `upload_fe_files` uploads `dist/` (majora pattern). Independent
    frontend deploys; needs a deploy target/credentials, two artifacts to keep in sync.

  **Interim rule (recorded in the spec):** until decided, `source/public/admin/` is git-ignored, so
  `make build-fe-release` leaves no untracked files. (The `.gitignore` line itself is added by the
  image/compose/Makefile sub-issue; reverting it is one line if A is chosen.)
- Codacy frontend coverage upload (partial + final) or not.

### Edge cases to document

- **Dev URL:** with `base: '/admin/'` in dev too, the Vite UI is at `http://localhost:8030/admin/`,
  not `:8030/`. Rejected alternative: `base` only for builds (shorter dev URL, but dev ≠ build).
- **`/admin` without a trailing slash:** `mod_dir` redirects `/admin` → `/admin/` as long as the
  front-controller rewrite skips existing **files and directories** (`!-f` and `!-d`). Update
  `architecture/backend.md` from "non-file requests" to "requests that are not existing files or
  directories", and reserve `/admin/` for the UI (the API router never claims it). The pending
  rewrite decision narrows to whether `/admin/` → `index.html` needs anything beyond `DirectoryIndex`.
- **Two UIs in dev:** `:8030/admin/` is the live Vite UI; `:8080/admin/` serves the last
  `make build-fe-release` output and can be stale. On a fresh clone there is no build, so the
  request falls through to `index.php` (JSON 404). Document this in `development.md` and `build.md`.
- **Fresh clone without `node_modules`:** `frontend_dev` does not start until `make fe-install`
  has run (the bind mount hides the image's install). `docker_volumes/node_modules/.gitkeep` keeps
  the folder owned by the host user rather than created by Docker as root.
- **Ports:** Vite listens on 8080 *inside* its container (host 8030); `app` is host 8080 but
  `app:80` on the compose network — the proxy target. State this in `development.md`.

### Security note

Document in `frontend/development.md`: the proxy keeps the browser on `localhost:8030`, so the PHP
session cookie is same-origin (`credentials: 'same-origin'`); keep `changeOrigin: false` and the
session cookie must not pin a `Domain`/port.

### Out of scope

Any code/infra change (sub-issues 2–4); adapting the citadel client layer to `/v1` (follow-ups).

### Testing

Docs only: links resolve, names match the shared contracts above, no spec still says "future work"
for the pieces being set up, and `grep -rn static docs/agents README.md` finds no reference to the
old `static/` frontend location.

## Benefits

- Sub-issues of #15 can be implemented (and reviewed) against a single agreed spec.
- Specs match the copied code, so the frontend agent isn't misled by stale names.
- Edge cases (dev URL, `/admin` slash, stale release bundle, ports) are documented up front.
