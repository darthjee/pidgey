# Frontend Testing

Purpose: summarize frontend test and lint tooling.

- Jasmine specs under `specs/assets/js/` mirroring `assets/js/`, named `*Spec.js`; helpers in
  `specs/support/`.
- Coverage: `yarn test` runs under nyc, `yarn coverage` under c8 (lcov).
- ESLint 9 flat config (`eslint.config.mjs`).
- Commands: `yarn test`, `yarn coverage`, `yarn lint`, `yarn lint_fix`.

Details in [frontend/testing.md](../frontend/testing.md).
