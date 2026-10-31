# Deployment Edge Cases

Purpose: list the failure modes of the production deployment and how each one is handled.

The flow these refer to is in [release.md](release.md).

## Staging and concurrency

- **Leftover staging folders:** a workflow that fails before `release` leaves its
  `${SSH_REMOTE_TEMP_DIR}-<workspace id>` folder behind. After a successful swap, `cleanup`
  removes other staging folders older than one day; recent ones may belong to a deploy in
  progress and are kept. Until `cleanup` is available (`darthjee/scripts:0.12.0`), stale
  folders are removed by hand.
- **Reruns:** supported. "Rerun from failed" keeps `CIRCLE_WORKFLOW_WORKSPACE_ID`, so it
  refills the same staging folder. `upload`, `link` (`ln -sfn`), `check-config` and `migrate`
  are safe to run again.
- **Concurrent tags:** the context jobs share one CircleCI `serial-group`, so deploys run one
  at a time, in the order they were queued. Rerunning or pushing an older tag redeploys that
  older code; its schema stays compatible under the expand/contract rule (see
  [Migrations](#migrations)).
- **A tag on a red commit:** every job of the deploy chain requires every check job, so nothing is deployed.
- **`SSH_KNOWN_HOSTS` unset:** the context jobs fail before any SSH call (see
  [security.md](security.md#host-keys)).

## Configuration

- **Missing or incomplete shared `.env`:** `bin/check-config` runs in staging before the
  swap. It loads `config/.env` and runs the boot-time configuration checks; a failure aborts
  the deploy with the live release untouched, instead of a site-wide 500 after the swap.
- **Wrong PHP version:** `bin/check-config` fails when the running PHP is not 8.4.x, which
  catches a wrong `PHP_BIN` or a host change before the swap. The web PHP version cannot be
  checked from the CLI; it is a manual check (see [server.md](server.md#to-confirm)).

## Migrations

- **Migrations ahead of live code:** migrations run before the swap, so a failure after them
  (or between them and the swap) leaves the old release running on the new schema. Rule:
  every migration must keep working with the **previous** release's code — expand first,
  contract (drop or rename) in a later release (see
  [architecture/database.md](../architecture/database.md)).
- **Failed migration:** each migration file is atomic and the run stops at the first failure.
  The deploy aborts before the swap, the live release is untouched, and the fix is a new
  migration in a new tag.

## Swap

- **Swap chain broken mid-way:** the swap moves live aside and then staging into live, with no
  rollback copy. The `release` action checks that staging exists before moving live aside, and
  staging is complete (uploads, `.env` link, `check-config`, migrations) before `release` runs.
  If the chain still breaks after live was moved aside, the site is down until recovered by
  hand on the host:

  ```bash
  mv <live>_old_<timestamp> <live>
  ```

  then fix the cause and push a new tag (or rerun the workflow).
- **Requests during the swap:** there is a millisecond window where the live folder does not
  exist. Accepted.
- **OPcache after the swap:** release paths do not change, so `opcache.validate_timestamps`
  must be on (the default) for PHP to pick up the new files (to confirm on the host, see
  [server.md](server.md#to-confirm)).

## Frontend

- **Stale admin `index.html` in browsers:** `public/admin/` serves `index.html` with
  `Cache-Control: no-cache`, so browsers revalidate it on every load and pick up the new
  hashed asset names. Hashed Vite assets stay cacheable.

## Pending decisions

None.
