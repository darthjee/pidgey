# Add the upload_codacy_coverage command
Under `commands:` in `.circleci/config.yml`, add `upload_codacy_coverage` with two string parameters:
- `report`: path to the coverage report, relative to the job's working dir;
- `prefix`: the repo folder the job flattened (`source` or `frontend`).

It has two `run` steps:

1. **Rewrite paths to be repo-relative.** This runs every time, not only when Codacy fails to map files. On `<< parameters.report >>`:
   - replace every `$(pwd)/` (the absolute flattened root, e.g. `/home/circleci/project/`) with `<< parameters.prefix >>/`. This covers Clover `<file name="...">` and absolute lcov `SF:` lines;
   - for lcov, turn `SF:./x` into `SF:<prefix>/x`, and turn a bare relative `SF:x` (not starting with `/` or with `<prefix>/`) into `SF:<prefix>/x`;
   - make it idempotent: running it twice must not double the prefix.
   Use `sed -i` (GNU sed; the images are Linux). Print a few rewritten lines (e.g. `grep -m3 -E 'SF:|file name=' <report>`) to make debugging easier.
2. **Upload a partial report**: `bash <(curl -Ls https://coverage.codacy.com/get.sh) report --partial -r << parameters.report >>`.

## Files to Change
- `.circleci/config.yml` — new `upload_codacy_coverage` command under `commands:`.
