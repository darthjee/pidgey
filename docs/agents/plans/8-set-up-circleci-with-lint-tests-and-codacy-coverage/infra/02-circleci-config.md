# CircleCI config

Create `.circleci/config.yml` (`version: 2.1`), modeled on Tent's config:

- `executors.pidgey-base`: `darthjee/circleci_pidgey-base:0.0.1`.
- `commands.prepare_source`: `checkout`, then `mv source old; mv old/* ./; rm -rf old`
  (also bring dotfiles if any are needed, e.g. none today).
- `commands.composer_setup`: `cp /home/circleci/vendor/ ./vendor/ -R`, then `composer install`.
- `jobs.backend_checks`: executor `pidgey-base`; `prepare_source`, `composer_setup`,
  `composer lint`.
- `jobs.backend_tests`: `docker:`
  - `darthjee/circleci_pidgey-base:0.0.1` with `environment` `PHP_ENV=test`, `DB_HOST=mysql`,
    `DB_PORT=3306`, `DB_NAME=pidgey_test`, `DB_USER=root`, `DB_PASSWORD=root`,
    `SMTP_HOST=mailpit`, `SMTP_PORT=1025`.
  - `mysql:9`, `name: mysql`, `environment: MYSQL_ROOT_PASSWORD: root`.
  - `axllent/mailpit`, `name: mailpit`.
  - Steps: `prepare_source`, `composer_setup`, `composer db:prepare`, `composer coverage`,
    Codacy upload `bash <(curl -Ls https://coverage.codacy.com/get.sh) report -r coverage/clover.xml`.
- `workflows.main`: both jobs, each with `filters: { tags: { only: /.*/ } }`, no `requires`
  (parallel).

Check which env vars the app actually reads (`Pidgey\Infra` config classes) so the names
match exactly.

## Files to Change
- `.circleci/config.yml` — new CircleCI pipeline.
