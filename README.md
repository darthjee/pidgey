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
make dev-up   # or: docker compose up app
```

See [`docs/agents/specs/docker/makefile.md`](docs/agents/specs/docker/makefile.md) for
the full list of `make` targets and platform notes (e.g. Apple Silicon).

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

## License

See [LICENSE](LICENSE).
