# Changelog

All notable changes to `pidgey-client` are listed here, in the
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) style, with versions per the
[versioning rules](../../docs/agents/specs/node-client/packaging.md#versioning). A release
heading is the bare version, without brackets: `## X.Y.Z` or `## X.Y.Z - YYYY-MM-DD`.

## Unreleased

## 0.1.0

### Added

- Initial release, for Node.js `>=22.12.0`, with no runtime dependencies.
- `PidgeyClient`, whose `send` sends one email with `POST /v1/messages`.
- `PidgeyError`, with the API and client-local error codes and `possiblySent`.
- Retries of transient failures (`maxRetries`, `retryAmbiguous`, `retryDelayMs`), with the
  caller's `Idempotency-Key` or a generated one.
- A per-attempt timeout (`timeoutMs`) and `AbortSignal` support.
- A guard against cleartext `http://` on non-loopback hosts, lifted by `allowInsecureHttp`.
- TypeScript declarations, with the API types generated from the OpenAPI document.
- The `VERSION` export.
