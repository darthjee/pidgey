# CI/CD Pipeline

Purpose: define the CircleCI jobs and how releases happen.

CI uses CircleCI (`.circleci/config.yml`) following the patterns of
https://github.com/darthjee/tent and https://github.com/darthjee/majora.

Every job runs in the CI image `circleci_pidgey-base` (see
[images](../docker/images.md)), not in `dev_pidgey`.

| Job | Steps |
| --- | --- |
| `backend_checks` | `composer lint` (phpcs + slevomat, phpmd). |
| `backend_tests` | Service containers `mysql:9` (named `mysql`) and `axllent/mailpit` (named `mailpit`); `composer db:prepare` (wait for the database, create `pidgey_test`, run migrations); `composer coverage` (PHPUnit unit + integration); upload `coverage/clover.xml` to Codacy with a single plain `report` command (no `--partial`/`final`) using `CODACY_PROJECT_TOKEN`. |
| `frontend_checks` | Future work. `yarn install`, `yarn lint`, `yarn test`, `yarn coverage`. |
| `build_images` | Future work. Build `dev_pidgey` (and `dev_pidgey-base` when changed). |
| `upload_fe_files` | Future work. `yarn install` → `vite build` → upload `dist/` to the web root `static/` (majora pattern). |
| `release` | Future work. Build and push the production `pidgey` image. |

For now images (`dev_pidgey-base`, `circleci_pidgey-base`) are built and pushed manually
(see [makefile](../docker/makefile.md)); there is no automated image build.

## Database access in CI

`backend_tests` connects to MySQL as **root** (`MYSQL_ROOT_PASSWORD=root` on the service;
`DB_HOST=mysql`, `DB_PORT=3306`, `DB_NAME=pidgey_test`, `DB_USER=root`, `DB_PASSWORD=root`,
`SMTP_HOST=mailpit`, `SMTP_PORT=1025`, `PHP_ENV=test` on the primary container). Why: CircleCI
service containers cannot run the `initdb.d` grant script used locally (see
[compose](../docker/compose.md)), and the tests create both `pidgey_test` and
`pidgey_runner_*_test` databases, which a restricted user could not do. The CI database is
throwaway, so root carries no risk.

## Workflow

`backend_checks` and `backend_tests` run in parallel on every branch **and every tag**.
`upload_fe_files` and `release` (future work) run only on `main` after all checks pass.

## Codacy

- Coverage is uploaded by `backend_tests` with `CODACY_PROJECT_TOKEN`, which is set only in
  the CircleCI project settings (never committed).
- Static-analysis exclusions live in `.codacy.yml` at the repository root (`exclude_paths`).

**Pending decisions:** production hosting target and upload mechanism for `dist/`;
image tagging scheme; how migrations are triggered on deploy.
