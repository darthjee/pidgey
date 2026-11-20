# CI test infrastructure and edge-case rules

Record how the client tests run, from the issue's "CI test infrastructure" and "Edge-case rules" sections.

- In `testing.md` "Approach", extend the local-server bullet: the server listens on `127.0.0.1` with port `0`, records every request (method, path, headers, body) for the spec to assert on, and is scripted per spec for the reply; it runs in `clients_checks` on a single `darthjee/circleci_node` container, with no secondary container. Add the rejected alternative (a mock server image such as WireMock, MockServer or an echo server) with its reasons.
- Add an "Edge-case rules" subsection (under "Approach" or as its own section):
  - clients under test target `127.0.0.1`, never `localhost` (IPv6-first resolution), with port `0` (no collisions in parallel runs);
  - teardown calls `server.closeAllConnections()` before `server.close()`;
  - the integration spec fails instead of skipping when the CI flag (for example `PIDGEY_INTEGRATION=required`) is set;
  - the items left to the implementation's judgment (minimal env for spawned CLI processes, SIGINT timing, the smoke test's temporary directory, Mailpit isolation, timing margins).
- In "Integration", state that `clients_integration` runs on a CircleCI machine executor and boots the stack with `docker compose` (Pidgey backend, MySQL, Mailpit), the same stack and commands as `make clients-integration`; that delivery is asserted through Mailpit's `GET /api/v1/messages`; that the "skipped locally" rule has the CI "required" flag exception. Add the two rejected executor options and the accepted trade-off (slower boot).

## Files to Change
- `docs/agents/specs/node-client/testing.md` — "Approach", new edge-case rules, "Integration".
