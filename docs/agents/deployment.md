# Deployment

Purpose: describe the production deployment **as implemented** (release `0.1.0`,
`darthjee/scripts:0.12.0`). These docs are the deployment reference; there is no deployment
spec. New deployment requirements are written in GitHub issues and their plans, and these docs
are updated in the same change that ships a deployment change (see
[contributing.md](contributing.md#deployment-changes)).

| File | Content |
| --- | --- |
| [pipeline.md](deployment/pipeline.md) | CircleCI check jobs, CI database access and Codacy, deploy chain, tag filter, `pidgey-deploy` context and its restriction, `serial-group`, `SSH_KNOWN_HOSTS` guard, deploy-script actions and version, exclude list and what ships, logs, who can deploy. |
| [variables.md](deployment/variables.md) | Every variable of the `pidgey-deploy` context, what it holds and its value format; the deploy key and host key. |
| [server.md](deployment/server.md) | Host folders (live, staging, shared), the `config/.env` link, the web directory, the shared `.env`, host requirements, the database users and HTTPS. |
| [operations.md](deployment/operations.md) | Releasing, rerunning, rotating keys, recovering a broken swap and removing stale staging folders. |
| [known-gaps.md](deployment/known-gaps.md) | What the current deployment does not cover yet, and where each gap is tracked. |
