# Backend exclude list

Create `.circleci/backend-exclude.txt`, the rsync `--exclude-from` list for `upload_backend` (spec: `docs/agents/specs/deployment/release.md#backend-upload-upload_backend`). It holds one pattern per line, anchored to the release root where it matters:

```text
/tests/
/coverage/
/phpcs.xml
/phpcs/
/phpmd*.xml
/phpdoc.dist.xml
/phpunit.xml
.env*
.git
```

Check it against the current `source/` top level: `bin composer.json composer.lock coverage lib migrations phpcs phpcs.xml phpdoc phpdoc.dist.xml phpmd_unusedcode.xml phpmd.xml phpunit.xml public tests vendor`.

- `phpdoc/` (the generated docs folder) is git-ignored, so a CI checkout never has it. Still, add `/phpdoc/` and `/.phpdoc/` defensively.
- Also add `/.phpunit.result.cache` and `/.phpunit.cache`, since only the "What ships" list may reach the host.
- What ships: `bin/`, `lib/`, `migrations/`, `public/`, `vendor/`, `composer.json`, `composer.lock`.

## Files to Change

- `.circleci/backend-exclude.txt`: new file.
