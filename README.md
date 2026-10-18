# Pidgey

Pidgey is an email-delivery service written in plain PHP. It sends transactional
email through SMTP connections configured per organization, exposes a JSON API
(authenticated by scoped API tokens) for sending messages, and ships a React
administrative UI.

Project documentation lives under [`docs/agents/`](docs/agents/); the entry point is
[`docs/agents/specs.md`](docs/agents/specs.md).

## First run

Everything runs in Docker. On a fresh clone:

```bash
export DOCKER_ID_USER=darthjee
make build-base && make build
docker compose run --rm tests composer install
make migrate  # create and migrate pidgey_development
make dev-up   # or: docker compose up app
```

See [`docs/agents/specs/docker/makefile.md`](docs/agents/specs/docker/makefile.md) for
the full list of `make` targets and platform notes (e.g. Apple Silicon).

## Database

MySQL runs in the `mysql` service. The `app` service uses `pidgey_development`; the `tests`
service (and therefore `make dev` / `make tests`) overrides `DB_NAME` to `pidgey_test`.

From the host (development database):

```bash
make migrate    # wait for MySQL, create pidgey_development if missing, apply pending migrations
make db-reset   # drop pidgey_development, then recreate and migrate it
```

Inside the `tests` container (`make dev`, `DB_NAME=pidgey_test`), from `/home/app/app`:

```bash
composer db:status                              # list applied / pending migrations (read-only)
composer db:migrate                             # apply pending migrations to pidgey_test
DB_NAME=pidgey_development composer db:migrate  # target the development database instead
```

`composer tests` and `composer tests:integration` prepare `pidgey_test` automatically (wait,
create, migrate); `composer tests:unit` needs no database.

Migrations live in `source/migrations/NNNN_description.sql`, with exactly one SQL statement per
file. They are forward-only: never edit or rename a file that has been applied, since applied
checksums are verified; add a new migration instead.

### Existing checkouts (one-time)

The application user is granted rights on every `pidgey_*` database by
`dockerfiles/mysql/initdb.d/01-grant-pidgey-dbs.sh`, which MySQL only runs on an empty data
volume. If your `docker_volumes/mysql_data` predates it, either reset the volume (this deletes
local data):

```bash
docker compose stop mysql
find docker_volumes/mysql_data -mindepth 1 ! -name .gitkeep -delete
docker compose up -d mysql
make migrate
```

or run the `GRANT` statement from that script manually as MySQL `root`.

## Tests and linters

From the host:

```bash
make tests                                   # composer lint && composer tests
docker compose run --rm tests composer tests # all PHPUnit suites, HTML coverage in source/coverage/
```

Inside the `tests` container (`make dev`), from `/home/app/app`:

```bash
composer tests                                   # all suites, with HTML coverage
composer tests:unit                              # unit suite only
composer tests:integration                       # integration suite only
composer tests -- tests/unit/Infra/EnvTest.php   # a single file
composer tests -- --filter EnvTest               # filter by test name
composer lint                                    # phpcs + phpmd
composer lint:fix                                # phpcbf, then phpmd
composer complexity                              # phpmd complexity report (complexity.html)
composer docs                                    # phpDocumentor output in phpdoc/
```

## Continuous integration

CircleCI ([`.circleci/config.yml`](.circleci/config.yml)) runs two jobs in parallel on every
branch and tag, inside the `darthjee/circleci_pidgey-base` image:

- `backend_checks` — `composer lint`.
- `backend_tests` — with MySQL 9 and Mailpit service containers: `composer db:prepare`,
  `composer coverage`, then uploads `coverage/clover.xml` to Codacy.

One-time setup (repository owner):

1. Build and push the CI image: `make build-ci-base && make push-ci-base` (needs Docker Hub
   credentials; the image is amd64-only and is built with `--platform linux/amd64`).
2. Enable the project on CircleCI and on Codacy.
3. Set `CODACY_PROJECT_TOKEN` in the CircleCI project environment variables (never commit it).

When `source/composer.lock` or `dockerfiles/circleci_pidgey-base/Dockerfile` changes, bump
`CI_BASE_VERSION` in the `Makefile` and the image tag in `.circleci/config.yml`, then build and
push the new image. Codacy exclusions live in [`.codacy.yml`](.codacy.yml).

## License

See [LICENSE](LICENSE).
