# Run the check in backend_checks

In `.circleci/config.yml`:

1. Set `ci_base_image` to `darthjee/circleci_pidgey-base:0.0.5`.
2. In `backend_checks`, after `composer_setup` and the lint step, add a step:

   ```yaml
   - run:
       name: OpenAPI breaking-change check
       command: |
         git fetch --quiet origin main
         scripts/check_openapi_breaking.sh openapi/v1.json origin/main
   ```

   - **Paths:** after `prepare_source`, the document sits at `openapi/v1.json`, and the root `scripts/` folder is untouched, because only `source/` is flattened. Check that `source/` has no `scripts/` folder that would collide.
   - **Refs:** CircleCI's `checkout` may not create `origin/main`, which is why the step fetches it. The repo is public, and the fetch reuses the checkout key, so no credential is added.
   - **Severity:** do not add `|| true`. Any `ERR` must fail the job.
3. `backend_checks` keeps no `context:`, and its workflow filters stay as they are (every branch and tag).

## Files to Change
- `.circleci/config.yml`: bump `ci_base_image` to `0.0.5`, and add the `OpenAPI breaking-change check` step to `backend_checks`.
