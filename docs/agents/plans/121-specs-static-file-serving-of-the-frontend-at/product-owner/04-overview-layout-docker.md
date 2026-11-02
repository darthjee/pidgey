# Overview, layout and Docker specs

Update the remaining specs that describe where the UI lives.

- `overview/components.md`: diagram and the Apache + PHP / React frontend rows — the UI is served at `/` by the PHP static-file handler from `source/static/`.
- `architecture/repository-layout.md`: `source/public/` row (only `index.php` and `.htaccess`; drop the stale "stub `index.php`" wording); new `source/static/` row (built UI, git-ignored, served by `StaticFileHandler`).
- `docker/makefile.md`: `FE_RELEASE_DIR` → `source/static`; dev URL `http://localhost:8030/`.
- `docker/compose.md`: `app` service description — serves the API and the built frontend at `/`.
- `docker/volumes.md`: release note — `make build-fe-release` fills `source/static/`, served by `app` through the `./source` mount.

## Files to Change

- `docs/agents/specs/overview/components.md`
- `docs/agents/specs/architecture/repository-layout.md`
- `docs/agents/specs/docker/makefile.md`
- `docs/agents/specs/docker/compose.md`
- `docs/agents/specs/docker/volumes.md`
