# Fixtures and loader

Create the shared JSON fixtures and the loader, then move the SMTP specs onto them. Today those
specs build their response objects inline.

- **Fixtures:** add `frontend/specs/support/fixtures/smtpConnections/*.json`, the 8 files of
  plan.md's "Example responses and fixtures" table, with exactly those bodies. Keep the JSON
  pretty-printed with 2 spaces.
- **Loader:** add `frontend/specs/support/loadFixture.js`:

  ```js
  loadFixture(name) // reads specs/support/fixtures/<name>.json and returns a fresh parsed copy
  ```

  It uses `fs.readFileSync` with a path resolved from `import.meta.url`, so it works whatever the
  cwd is. Each call returns a new object.
- **Loader spec:** add `frontend/specs/support/loadFixtureSpec.js`. It checks that
  `loadFixture('smtpConnections/item')` returns the item, that two calls return distinct objects,
  and that an unknown name throws.
- **Move the specs:** switch the existing SMTP specs from inline responses to fixtures, building
  `ApiError`s from the error fixtures with
  `new ApiError(body.statusCode, body.error.message, body.error.code, body.error.details, body.error.smtp)`.
  The specs to update are under `frontend/specs/assets/js/client/SmtpConnectionsClientSpec.js`
  and `frontend/specs/assets/js/components/resources/smtpConnections/**`, mainly
  `SmtpConnectionsControllerSpec`, `SmtpConnectionTestControllerSpec` and
  `SmtpConnectionFormControllerSpec`. Keep the ad-hoc values a test needs on purpose, such as an
  unusual field, by spreading over the fixture.

## Files to Change

- `frontend/specs/support/fixtures/smtpConnections/item.json`, `list.json`, `testSent.json`,
  `validationFailed.json`, `smtpFailure.json`, `sendTimeFailure.json`, `tooManyRequests.json`,
  `notFound.json` — new.
- `frontend/specs/support/loadFixture.js` — new.
- `frontend/specs/support/loadFixtureSpec.js` — new.
- `frontend/specs/assets/js/client/SmtpConnectionsClientSpec.js` — use the fixtures.
- `frontend/specs/assets/js/components/resources/smtpConnections/**/*Spec.js` — use the
  fixtures wherever they build API responses or errors.
