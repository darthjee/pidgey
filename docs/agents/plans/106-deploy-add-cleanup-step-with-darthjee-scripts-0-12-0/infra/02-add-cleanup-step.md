# Add the cleanup step to the release job
Append a step to the `release` job in `.circleci/config.yml`, after the `Release` step:

```yaml
      - run:
          name: Cleanup
          command: deploy_frontend.sh cleanup
```

Make it a plain step: no `when: always` and no `|| true`. If cleanup fails, the job and the workflow fail, even though the new release is already live. No new CircleCI context variables are expected; the existing `pidgey-deploy` context already provides `SSH_REMOTE_TEMP_DIR` and the SSH settings.

## Files to Change
- `.circleci/config.yml` — the new `Cleanup` step at the end of the `release` job.
