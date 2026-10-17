# CI/CD Pipeline

Purpose: define the CircleCI jobs and how releases happen.

CI uses CircleCI (`.circleci/config.yml`) following the patterns of
https://github.com/darthjee/tent and https://github.com/darthjee/majora.

Every job runs in the CI image `circleci_pidgey-base` (see
[images](../docker/images.md)), not in `dev_pidgey`.

| Job | Steps |
| --- | --- |
| `backend_checks` | `composer lint` (phpcs + slevomat, phpmd). |
| `backend_tests` | MySQL and Mailpit service containers; wait for the database; create the test database; run migrations; `composer coverage` (PHPUnit unit + integration); upload the Clover report to Codacy with a plain `report` command using `CODACY_PROJECT_TOKEN` (set only in CircleCI). |
| `frontend_checks` | Future work. `yarn install`, `yarn lint`, `yarn test`, `yarn coverage`. |
| `build_images` | Future work. Build `dev_pidgey` (and `dev_pidgey-base` when changed). |
| `upload_fe_files` | Future work. `yarn install` → `vite build` → upload `dist/` to the web root `static/` (majora pattern). |
| `release` | Future work. Build and push the production `pidgey` image. |

For now images (`dev_pidgey-base`, `circleci_pidgey-base`) are built and pushed manually
(see [makefile](../docker/makefile.md)); there is no automated image build.

Workflow: checks run on every branch; `upload_fe_files` and `release` run only on
`main` after all checks pass.

**Pending decisions:** production hosting target and upload mechanism for `dist/`;
image tagging scheme; how migrations are triggered on deploy.
