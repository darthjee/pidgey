# Declaration build and package shape

Emit the `.d.ts` declarations into `dist/`, and ship them.

- Add `tsconfig.build.json`:
  - `allowJs`, `declaration`, `emitDeclarationOnly`, `strict`;
  - `module`/`moduleResolution: nodenext`, `target: es2022`;
  - `rootDir: "."`, `outDir: "dist"`, `include: ["lib", "types"]`.
- It emits `dist/lib/*.d.ts` and `dist/types/v1.d.ts`, so the relative `../types/v1.js` imports in the declarations resolve inside the tarball.
- Add the script `build:types`: `rm -rf dist && tsc -p tsconfig.build.json`. No `prepack` hook: the smoke test packs with `--ignore-scripts`.
- `package.json`:
  - `exports`: `{ ".": { "types": "./dist/lib/index.d.ts", "default": "./lib/index.js" } }`;
  - `files`: `["lib/", "dist/"]`.
- Run `yarn build:types && npm pack --dry-run`, and add every emitted `.d.ts` path to `pack-files.txt` (sorted with `LC_ALL=C`).
- Add `dist/**` to the ESLint `ignores`.

## Files to Change

- `clients/nodejs/tsconfig.build.json`: new.
- `clients/nodejs/package.json`: `build:types`, `exports`, `files`.
- `clients/nodejs/pack-files.txt`: the `.d.ts` entries.
- `clients/nodejs/eslint.config.mjs`: ignore `dist/**`.
