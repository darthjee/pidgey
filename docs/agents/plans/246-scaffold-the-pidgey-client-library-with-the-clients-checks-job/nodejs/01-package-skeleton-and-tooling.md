# Package skeleton and tooling

Create the package and its development tooling. Follow `docs/agents/specs/node-client/packaging.md` (Packages, Stack, Node.js version, Versioning, Supply chain) and `testing.md` (Coverage threshold, JSDoc check of the source).

- `package.json`:
  - name, version, type, license, `description`, `repository` (this repository, `directory: clients/nodejs`), `exports`, `engines`, `files`;
  - no `dependencies`, and no `preinstall` / `install` / `postinstall` / `prepare` scripts;
  - scripts:
    - `lint`: `eslint lib specs`;
    - `test`: `jasmine`;
    - `coverage`: `c8 jasmine`;
    - `typecheck`: `tsc --allowJs --checkJs --noEmit -p .`, or the equivalent with a `jsconfig.json`;
  - a `jasmine` block (`spec_dir: specs`, `spec_files: ["**/*_spec.js"]`);
  - a `c8` block: reporters `text` + `lcov`, `include: ["lib/**/*.js"]`, `all: true`, `check-coverage: true`, `lines` / `branches` / `functions` / `statements` all `100`.
- devDependencies:
  - `eslint` 9 with `@eslint/js`, `eslint-plugin-jsdoc`, `eslint-plugin-jasmine`, `globals`;
  - `jasmine`, `c8`, `typescript`.

  Pin them as `frontend/package.json` does. No React or Babel.
- `eslint.config.mjs`: a flat config modelled on `frontend/eslint.config.mjs`, without React. It has Node globals, Jasmine globals for `specs/`, and `jsdoc` rules requiring JSDoc on exported symbols.
- `tsconfig.json` (or `jsconfig.json`): `checkJs`, `allowJs`, `noEmit`, `strict`, `module` / `moduleResolution: nodenext`, `target: es2022`, `include: ["lib"]`.
- `yarn.lock`, generated in the compose `clients` service.
- `.gitignore` (root): add `clients/*/node_modules/` and `clients/*/coverage/`.

## Files to Change
- `clients/nodejs/package.json`: new.
- `clients/nodejs/yarn.lock`: new.
- `clients/nodejs/eslint.config.mjs`: new.
- `clients/nodejs/tsconfig.json`: new.
- `.gitignore`: ignore the clients' `node_modules/` and `coverage/`.
