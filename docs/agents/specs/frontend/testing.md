# Frontend Testing and Linting

Purpose: define the frontend test, coverage and lint setup.

- Jasmine specs in `frontend/specs/` mirror `assets/js/` (one spec per source file).
- Coverage with c8.
- ESLint 9 flat config (`eslint.config.js`) with plugins complexity, jsdoc, react,
  react-hooks, jasmine.

`package.json` scripts:

| Script | Action |
| --- | --- |
| `build` | `vite build` |
| `server` | `vite` dev server (port 8030 in docker-compose) |
| `test` | Run Jasmine |
| `coverage` | Run Jasmine under c8 |
| `lint` | Run ESLint |

See [testing](../testing.md).
