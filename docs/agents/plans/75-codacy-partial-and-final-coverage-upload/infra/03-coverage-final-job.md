# Add the coverage_final job
Add a `coverage_final` job:
- `docker: - image: darthjee/circleci_node:0.2.1`;
- no checkout;
- a single step that runs `bash <(curl -Ls https://coverage.codacy.com/get.sh) final`.

In `workflows.main.jobs`, add:

```yaml
- coverage_final:
    requires: [backend_tests, frontend_checks]
    filters: *all_tags
```

## Files to Change
- `.circleci/config.yml` — new `coverage_final` job and workflow entry.
