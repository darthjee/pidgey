# MySQL grant init script and compose wiring

Give `DB_USER` the privileges to create/drop `pidgey_*` databases, and point the `tests`
service at the test database.

- `dockerfiles/mysql/initdb.d/01-grant-pidgey-dbs.sh` (mode 0755), roughly:

  ```sh
  #!/bin/sh
  # Lets the application user create and drop every pidgey_* database
  # (pidgey_development, pidgey_test, throwaway pidgey_runner_*_test DBs).
  docker_process_sql --database=mysql <<-EOSQL
  	GRANT ALL ON \`pidgey\\_%\`.* TO '${MYSQL_USER}'@'%';
  EOSQL
  ```

  Verify the statement MySQL actually receives is ``GRANT ALL ON `pidgey\_%`.* TO 'pidgey'@'%'``
  (`SHOW GRANTS FOR 'pidgey'@'%'` after a fresh init).
- `docker-compose.yml`:
  - `mysql.volumes`: add `- ./dockerfiles/mysql/initdb.d:/docker-entrypoint-initdb.d:ro`.
  - `tests.environment`: add `DB_NAME: pidgey_test`.
  - No compose `command:` change for the tests flow — `composer tests` prepares the DB itself.

## Files to Change
- `dockerfiles/mysql/initdb.d/01-grant-pidgey-dbs.sh` — new.
- `docker-compose.yml` — mysql init mount, `tests` `DB_NAME` override.
