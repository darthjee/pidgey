# Frontend Stack

Purpose: fix the libraries and tooling of the admin UI.

| Concern | Choice |
| --- | --- |
| UI library | React 19 |
| Bundler / dev server | Vite 7 |
| Components | react-bootstrap 2.x (Bootstrap 5) |
| Icons | bootstrap-icons |
| Language | Plain JavaScript (`.js`/`.jsx`), **no TypeScript** |
| Package manager | Yarn |
| Compiler | babel-plugin-react-compiler |
| Tests | Jasmine + c8 |
| Lint | ESLint 9 flat config, plugins: complexity, jsdoc, react, react-hooks, jasmine |

Reference: https://github.com/darthjee/citadel `frontend/`.
