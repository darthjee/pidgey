# Write the breaking-change script and make target

Write `scripts/check_openapi_breaking.sh`, in the same style as `scripts/bump-version.sh`:
- a header comment with usage;
- `set -euo pipefail`.

What the script does:

1. **Arguments:** `$1` is the head document path (required) and `$2` is the base ref (default `origin/main`). On a missing argument, print usage and exit `2`.
2. **Base:**
   - `merge_base=$(git merge-base "$base_ref" HEAD)`. If that fails, print a clear error and exit non-zero.
   - Make a work dir with `mktemp -d`, cleaned up with a `trap`.
   - `git show "$merge_base:source/openapi/v1.json" > "$work/base.json"`. If that fails, print "base document not found at <merge-base>" and exit non-zero. The path is the repo path, which is the same in CI because `prepare_source` only moves the working tree, not git history.
   - Copy the head document to `$work/head.json`.
3. **Run:**
   - If `oasdiff` is on `PATH`, run `oasdiff breaking "$work/base.json" "$work/head.json" --fail-on ERR`.
   - Otherwise, use `docker run --rm -v "$work:/work:ro" tufin/oasdiff:${OASDIFF_VERSION:?} breaking /work/base.json /work/head.json --fail-on ERR`.
   - If neither is available, fail.
4. Print a one-line summary before running: the base ref, the merge-base sha and both paths. Pass oasdiff's exit status through.

On `main` (or a tag on `main`), the merge-base is `HEAD`, the documents are identical, and the check passes.

Add a `make openapi-diff` target (and list it in `.PHONY`):

```make
openapi-diff:
	git fetch --quiet origin main
	OASDIFF_VERSION=$(OASDIFF_VERSION) scripts/check_openapi_breaking.sh source/openapi/v1.json origin/main
```

Verify on the branch:
- `make openapi-diff` passes;
- the manual removed-field and added-optional-field checks from the Notes in [infra.md](../infra.md) behave as expected;
- revert both edits.

## Files to Change
- `scripts/check_openapi_breaking.sh` (new, executable): the diff against the merge-base.
- `Makefile`: the `openapi-diff` target and its `.PHONY` entry.
