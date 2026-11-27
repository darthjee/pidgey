# CircleCI jobs, filters and serial group

In `.circleci/config.yml`:

1. **Filter anchors**, next to `deploy_tags`:
   - `client_tags`: `tags: only: /^pidgey-client-v\d+\.\d+\.\d+$/`, `branches: ignore: /.*/`;
   - `cli_tags`: `tags: only: /^pidgey-cli-v\d+\.\d+\.\d+$/`, `branches: ignore: /.*/`.
   Neither matches `deploy_tags` (`^\d+\.\d+\.\d+$`), and the deploy jobs keep `deploy_tags`.
2. **Jobs** (Docker `cimg/node:22.12.0`, the image already used by `clients_checks`, with Yarn and npm):
   - `pack_client` / `pack_cli`: no context. `checkout`, then `clients/scripts/release_pack.sh "$CIRCLE_TAG" release`, then `persist_to_workspace` (`root: .`, `paths: [release]`). A single parameterized `pack_package` job is fine if it stays readable; the workflow entries keep the names `pack_client` / `pack_cli`.
   - `publish_client` / `publish_cli`: no `checkout` is needed beyond the script itself. Either check out to get `clients/scripts/release_publish.sh`, or persist the script in the workspace from the pack job. Running a committed shell script is not third-party code, and neither job runs `yarn` or `npm install`. `attach_workspace` at `/tmp/workspace`, then `clients/scripts/release_publish.sh /tmp/workspace/release/*.tgz` (assert exactly one tarball).
3. **Workflow entries**:
   - `pack_client` / `pack_cli`: `requires: [backend_checks, backend_tests, frontend_checks, clients_checks, clients_integration]`, filters `*client_tags` / `*cli_tags`. Requiring `clients_checks` covers every matrix instance.
   - `publish_client` / `publish_cli`: `requires` their pack job, `context: pidgey-npm`, `serial-group: << pipeline.project.slug >>/pidgey-npm`, same filters.
4. Comment the block briefly, pointing at `docs/agents/deployment/pipeline.md`.

## Files to Change
- `.circleci/config.yml` — anchors, four jobs, workflow entries.
