# CI/CD Pipeline

Purpose: define the CircleCI jobs and how releases happen.

CI uses CircleCI (`.circleci/config.yml`) following the patterns of
https://github.com/darthjee/tent and https://github.com/darthjee/majora.

| Job | Steps |
| --- | --- |
| `backend_checks` | In `dev_pidgey`: `composer lint` (phpcs + slevomat), phpmd, `composer tests` (PHPUnit unit + integration with MySQL and Mailpit service containers), coverage. |
| `frontend_checks` | `yarn install`, `yarn lint`, `yarn test`, `yarn coverage`. |
| `build_images` | Build `dev_pidgey` (and `dev_pidgey-base` when changed). |
| `upload_fe_files` | `yarn install` → `vite build` → upload `dist/` to the web root `static/` (majora pattern). |
| `release` | Build and push the production `pidgey` image. |

Workflow: checks run on every branch; `upload_fe_files` and `release` run only on
`main` after all checks pass.

**Pending decisions:** production hosting target and upload mechanism for `dist/`;
image tagging scheme; how migrations are triggered on deploy.
