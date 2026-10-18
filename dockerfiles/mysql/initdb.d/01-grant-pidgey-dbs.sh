#!/bin/sh
# Lets the application user create and drop every pidgey_* database
# (pidgey_development, pidgey_test, throwaway pidgey_runner_*_test DBs).
#
# Runs only on an empty docker_volumes/mysql_data. The official image executes
# (rather than sources) init scripts that are executable, so the entrypoint's
# docker_process_sql helper is not available here; talk to the temporary
# init server through its socket with the mysql client instead.
set -e

MYSQL_PWD="${MYSQL_ROOT_PASSWORD}" mysql --protocol=socket -uroot --database=mysql <<EOSQL
GRANT ALL ON \`pidgey\\_%\`.* TO '${MYSQL_USER}'@'%';
EOSQL
