# TLS fixtures

Create `source/tests/fixtures/tls/generate.sh`, a POSIX `sh` script using `openssl`, and run
it once to commit the generated files.

The script:

- runs from its own directory and overwrites the files listed in
  [plan.md](../plan.md#tls-fixture-files-sourcetestsfixturestls);
- creates a new test CA (`CN=Pidgey Test CA`, `basicConstraints=critical,CA:true`) and
  deletes the CA key at the end, so the key is never committed;
- signs `server.pem` (SAN `mailpit-starttls`, `mailpit-ssl`, `localhost`) and
  `wrong-name.pem` (SAN `wrong-name.invalid`) with the CA, both with
  `extendedKeyUsage=serverAuth`;
- creates `untrusted.pem` as a self-signed certificate for `localhost`;
- gives every certificate 100-year validity (`-days 36500`) and an RSA 2048 key;
- makes the key files readable by everyone (`0644`). They are test-only, and the Mailpit
  container user must read them through the mount.

Add a short header comment saying the material is test-only and must never be used outside
tests.

## Files to Change

- `source/tests/fixtures/tls/generate.sh` — new regeneration script.
- `source/tests/fixtures/tls/{ca,server,server-key,wrong-name,wrong-name-key,untrusted,untrusted-key}.pem` — generated, committed.
