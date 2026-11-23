# Nodejs Plan: Scaffold the pidgey-client library with the clients_checks job

Main plan: [plan.md](plan.md)

## Shared contracts

- **Produce:**
  - `clients/nodejs/`, with `lib/index.js`, `lib/PidgeyClient.js`, `lib/PidgeyError.js` and `specs/**/*_spec.js`.
  - `package.json`:
    - `pidgey-client`, `0.1.0`, ESM, MIT;
    - `exports` `{ ".": "./lib/index.js" }`, `engines` `>=22.12.0`, `files` `["lib/"]`;
    - no runtime dependency and no install scripts.
  - Scripts `lint`, `typecheck`, `test` and `coverage`. `coverage` writes `coverage/lcov.info` and enforces 100 % (`check-coverage: true`, `all: true`, `include: ["lib/**/*.js"]`).
  - The committed `yarn.lock`.
  - `clients/nodejs/pack-files.txt`: the sorted (`LC_ALL=C`) `files[].path` of `npm pack --dry-run --json`.
- **Rely on:** infra's `clients/scripts/checks.sh` and the compose `clients` service (`docker compose run --rm clients sh -c "cd clients/nodejs && yarn …"`) to run all of this.

## Steps

- [01 — Package skeleton and tooling](nodejs/01-package-skeleton-and-tooling.md)
- [02 — Stub classes, specs and README](nodejs/02-stub-classes-specs-and-readme.md)
- [03 — Expected pack file list](nodejs/03-expected-pack-file-list.md)

## CI Checks

- `clients/`: `make clients-checks` (CI job: `clients_checks`, on Node.js 22.12.0 and 22.14.0).

## Notes

- Run `yarn` inside the compose `clients` service (Node 22.x), never with a host Node of another major. Commit `yarn.lock` (Yarn classic, like `frontend/`).
- `typescript` is a devDependency, for `yarn typecheck` only. Nothing is generated or shipped yet: #250 adds the `.d.ts` build and the `types` entry of `exports`.
- Do not add `send`, options validation or error mapping: that is #247.
