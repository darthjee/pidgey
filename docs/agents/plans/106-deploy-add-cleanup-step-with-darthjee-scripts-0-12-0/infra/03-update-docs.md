# Update specs and deployment docs
Remove every "`cleanup` is missing / until `0.12.0`" statement and record the new versions.

## Files to Change
- `docs/agents/specs/docker/images.md` — the deploy tooling version is `0.12.0`. Drop the "not released yet" sentence, and update the image-version mention (`starting with version 0.0.2`) if it is meant to track the current tag.
- `docs/agents/specs/docker/makefile.md` — `CI_BASE_VERSION` is `0.0.3`. Note that `0.0.3` moved the deploy tooling to `scripts:0.12.0`.
- `docs/agents/specs/deployment/release.md` — the version is `0.12.0`. Drop the "if it is not released in time" fallback paragraph, and drop "(from `0.12.0`)" in the flow list. Note that a cleanup failure fails the job.
- `docs/agents/specs/deployment/edge-cases.md` — drop "Until `cleanup` is available … removed by hand", and point to the operations runbook as the fallback.
- `docs/agents/deployment/pipeline.md` — the version is `0.12.0` in "Deploy script". Add a `cleanup` row (job `release`, no extra variables) to the action table, replace the "No `cleanup`" note, and say the job fails if cleanup fails.
- `docs/agents/deployment/known-gaps.md` — remove the "No `cleanup` action" section.
- `docs/agents/deployment/operations.md` — reword "Removing stale staging folders" as a fallback: use it when the `Cleanup` step failed, or to remove a dead folder younger than one day. Keep the "never remove a running workflow's folder" check.
