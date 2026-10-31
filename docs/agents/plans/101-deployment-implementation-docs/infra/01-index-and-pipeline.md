# Index and pipeline
Create `docs/agents/deployment.md`: a short index with a purpose line saying these docs describe the deployment as implemented and replace the deployment specs as the reference, plus a table of the five topic files.

Create `docs/agents/deployment/pipeline.md`. Read it off `.circleci/config.yml`:
- the check jobs, and the deploy chain `build_backend`/`build_frontend` → `upload_backend` → `upload_frontend` → `release`, with what each `requires`;
- the tag filter `/^\d+\.\d+\.\d+$/`, with branches ignored, and check jobs running on every branch and tag;
- the build jobs without a context, and the context jobs with `pidgey-deploy`. Explain why: third-party code never runs next to the deploy key. Builds hand their output over through the workspace;
- the context's expression restriction, `pipeline.git.tag =~ "^[0-9]+\.[0-9]+\.[0-9]+$"`, set in the CircleCI organization settings (security groups are not available on the free plan);
- `serial-group: << pipeline.project.slug >>/pidgey-deploy`;
- the `deploy_setup` command: the `SSH_KNOWN_HOSTS` guard, then `deploy_frontend.sh generate_key_file`;
- the `deploy_frontend.sh` actions used: `upload` (with `SOURCE`, `EXCLUDE_FROM`, `DEPLOY_PATH`), `link`, `run` (`COMMAND`) and `release`. State the script version (`darthjee/scripts:0.11.0`, through `SCRIPTS_VERSION` in `circleci_pidgey-base`), and say there is no `cleanup` until `0.12.0`;
- the exclude list (`.circleci/backend-exclude.txt`) and what it keeps out;
- who can deploy: anyone who can push a semver tag. That is only the owner today, the sole collaborator, so there is no tag ruleset.

## Files to Change
- `docs/agents/deployment.md` — new index.
- `docs/agents/deployment/pipeline.md` — new.
