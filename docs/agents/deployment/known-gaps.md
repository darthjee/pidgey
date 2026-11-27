# Known gaps

List what the current deployment does not cover yet, why, and where each gap is
tracked.

## No proxy rate limiting

The public auth endpoints (`/auth/*.json`: login, register, recover, reset-password) have no
application-level throttle in v1 (see
[api/endpoints/auth/session.md](../api/endpoints/auth/session.md#brute-force)).
Deployments behind a configurable reverse proxy should rate-limit `/auth/*.json` per client IP
there, to slow down password guessing and registration abuse.

The current host is a shared host with no configurable reverse proxy, so this cannot be
applied. To limit registration abuse, production keeps `USER_APPROVAL_REQUIRED=true` (see
[server.md](server.md#shared-env)).

Application-level throttling is an open decision owned by
[api/pending.md](../api/pending.md) (rate limits).

## Migration credentials in the shared `.env`

`DB_MIGRATE_USER` / `DB_MIGRATE_PASSWORD` live in the same shared `.env` as the app settings
(see [server.md](server.md#shared-env)). `public/index.php` loads that file on every request,
so the web process has the migration user's credentials in its environment. The split
between the two users stops SQL issued by the app (e.g. SQL injection) from altering or
dropping the schema. It does not protect against a file-read or code-execution flaw in the
web app, which could read those credentials. Keeping them in a separate file that only the
`bin/` schema commands load would close this; it is not tracked yet.

## `TRUSTED_PROXY_HOPS` not verified

The shared `.env` sets `TRUSTED_PROXY_HOPS=0` because no reverse proxy sits in front of Apache
on this host (see [server.md](server.md#shared-env)). That cannot be checked from outside until
an endpoint shows the client IP the app resolved. If a proxy turns out to be there, the value
is wrong and clients behind it all share the proxy's IP.

## SMTP total timeout vs. the proxy timeout

`SMTP_TOTAL_TIMEOUT` (default `25` seconds) must stay below the request timeout of Apache and
of any reverse proxy in front of it (see [server.md](server.md#shared-env)). Apache's timeout
on this shared host, and whether a proxy with its own shorter timeout exists (see
[`TRUSTED_PROXY_HOPS` not verified](#trusted_proxy_hops-not-verified)), are not verified. If
either is shorter, a slow SMTP server makes the request die with a gateway error instead of a
`504 SMTP_TIMEOUT` envelope, and no attempt is logged. Lower `SMTP_TOTAL_TIMEOUT` if that is
seen. A client that retries `POST /v1/messages` with the same `Idempotency-Key` gets the stored
outcome replayed instead of a second delivery (see
[sending.md](../api/endpoints/sending.md#idempotency-key)); only a request that died before the
key was reserved is sent again.

## DNS resolution is not bounded by the SMTP timeouts

`SMTP_CONNECT_TIMEOUT` and `SMTP_TOTAL_TIMEOUT` bound the TCP connect and the SMTP session,
but not the DNS lookup of the SMTP `host`: PHP's resolver uses the system resolver timeout,
which the app cannot set. A slow or unreachable DNS server can therefore delay a test email
beyond `SMTP_TOTAL_TIMEOUT`. Not tracked yet; the host's resolver is assumed to answer quickly.

## SMTP host check does not restrict ports or public hosts

With `SMTP_ALLOW_PRIVATE_HOSTS=false` (see [server.md](server.md#shared-env)), a send to an
SMTP host that resolves to a non-public address is refused before any connection (see
[email/smtp-connections.md](../email/smtp-connections.md)). Ports are not restricted,
and public hosts are allowed, so the test email still acts as a reachability oracle: an
approved user can learn whether a public host accepts TCP connections on a given port from
the error code and timing. The per-organization test-email rate limit (`SMTP_TEST_MAX_ATTEMPTS`
per `SMTP_TEST_WINDOW`) slows this down but does not remove it. Not tracked yet.

Setting `SMTP_ALLOW_PRIVATE_HOSTS=true` in production (for an internal relay) removes the
check entirely and reopens server-side request forgery to internal addresses.

## Test-email lease does not bound workers across organizations

At most one test email is in flight per organization (a lease in `smtp_test_limits`), so one
organization cannot tie up more than one PHP worker with slow SMTP servers. Many
organizations can still each hold one at the same time: nothing bounds the total number of
workers busy with test emails across organizations, and on the shared host that can exhaust
the worker pool for up to `SMTP_TOTAL_TIMEOUT` seconds per request. With
`USER_APPROVAL_REQUIRED=true` only approved users can send test emails, which limits who can
do it. A global limit belongs to the open rate-limit decision in
[api/pending.md](../api/pending.md).

## npm publishing not set up or exercised yet

The [npm publish chain](pipeline.md#npm-publish-chain) is in the repository, but its real
publish path (token, upload, wait for npm) has never run: only dry runs and runs against a
stand-in `npm`. On 2026-11-27, `npm view` answered "not found" for `pidgey-client` and
`pidgey-cli`. The first release, `0.1.0` (#254), is a
[manual publish](operations.md#publish-the-first-client-release-manually) that does not go
through the chain: no `0.0.0` placeholder, token or `pidgey-npm` context is set up. The CI
publish path stays unexercised until the switch to trusted publishing (#266).

## npm token publishing ends in January 2027

npm announces in [about access tokens](https://docs.npmjs.com/about-access-tokens) (read
2026-11-27): "The ability to publish new package versions directly with a granular access
token will be removed in **January 2027**." The publish jobs publish exactly that way, so
from then on they stop working, and rotating the token will not help. The token path is
therefore not set up for the first release, which is published by hand (#254), and CI
publishing depends on the switch to npm trusted publishing (OIDC), which supports CircleCI:
#266.

## npm packages published without provenance

The packages are published without a provenance attestation, so a consumer cannot verify that
a tarball was built from a commit of this repository.

- `npm publish --provenance` supports only GitHub Actions and GitLab CI/CD
  ([generating provenance statements](https://docs.npmjs.com/generating-provenance-statements),
  read 2026-11-27).
- npm trusted publishing supports CircleCI, but "Provenance generation is not currently
  supported for CircleCI" ([trusted publishers](https://docs.npmjs.com/trusted-publishers),
  read 2026-11-27). The switch of #266 therefore does not close this gap.

It closes when npm supports provenance from CircleCI. Publishing from a second CI system was
rejected when the mechanism was chosen (#223).

## Releasing both clients at once

`publish_client` and `publish_cli` share one
[serial group](pipeline.md#serial-group), and `publish_cli` holds it while it waits for the
library. With both tags pushed at once, if `publish_cli` takes the group first it waits for a
library version that cannot be published meanwhile, and fails after 5 minutes. The library
publish then runs after it, or is **skipped** if its pipeline number is lower. Nothing wrong
is published; the workflows are rerun one at a time.

Until that changes, push the CLI tag only after the library is published (see
[operations.md](operations.md#release-a-client-package)). One serial group per package would
remove the restriction; it is not tracked yet.

## Client tags are not protected

No GitHub tag ruleset covers `pidgey-client-v*` / `pidgey-cli-v*`, and a tag may point at any
commit, not only one of `main`. The pipeline configuration comes from that commit, so whoever
can push such a tag can run any job with the `pidgey-npm` context, whatever the committed
release scripts check. Today only the owner can push. Not tracked yet.

## npm type declarations are not verified at publish

The publish job rebuilds the tarball from the checkout and compares the bytes, but the
library's `dist/*.d.ts` files are build output of the pack job, which runs third-party code.
Their names, count, file type and mode are enforced, their content is not. They are type
declarations, never executed. Not tracked yet.

## npm publish job hardening

- When npm already serves the version, the publish job succeeds without comparing the
  published content with the tarball, so a version published by other means also reports
  success.
- The `client-release` image is referenced by a tag, not pinned by digest.
- `NPM_TOKEN` is in the environment of every step of the publish job, not only of the publish
  command.

Not tracked yet.

## Deploy context restriction

The `pidgey-deploy` [restriction](pipeline.md#context-restriction) documented here checks only
the tag: it has no project restriction and does not exclude SSH reruns, both of which the
`pidgey-npm` context has. Not tracked yet.

## No automated image builds

The images CI and development use (`dev_pidgey-base`, `circleci_pidgey-base`,
`vite_pidgey-base`) are built and pushed by hand with the Makefile's `build-*` / `push-*`
targets (see [docker/makefile.md](../docker/makefile.md)). There is no `build_images`
job: a change to a Dockerfile reaches CI only after the owner rebuilds the image, pushes it
with a bumped version and updates the tag in `.circleci/config.yml` (for example
`ci_base_image`). Bumping the deploy script works the same way (see
[pipeline.md](pipeline.md#deploy-script)).

It is open because images change rarely and a manual push is enough so far. An automated
`build_images` job is future work, not tracked by an issue yet.
