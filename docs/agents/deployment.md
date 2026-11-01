# Deployment

Purpose: describe the production deployment **as implemented** (release `0.1.0`,
`darthjee/scripts:0.12.0`). These docs replace the deployment specs as the reference; where
the two differ, these docs describe what actually runs.

| File | Content |
| --- | --- |
| [pipeline.md](deployment/pipeline.md) | CircleCI check jobs, CI database access and Codacy, deploy chain, tag filter, `pidgey-deploy` context and its restriction, `serial-group`, `SSH_KNOWN_HOSTS` guard, deploy-script actions and version, exclude list and what ships, logs, who can deploy. |
| [variables.md](deployment/variables.md) | Every variable of the `pidgey-deploy` context, what it holds and its value format; the deploy key and host key. |
| [server.md](deployment/server.md) | Host folders (live, staging, shared), the `config/.env` link, the web directory, the shared `.env`, host requirements, the database users and HTTPS. |
| [operations.md](deployment/operations.md) | Releasing, rerunning, rotating keys, recovering a broken swap and removing stale staging folders. |
| [known-gaps.md](deployment/known-gaps.md) | What the current deployment does not cover yet, and where each gap is tracked. |
