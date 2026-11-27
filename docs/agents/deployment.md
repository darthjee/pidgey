# Deployment

Describe the production deployment **as implemented** (release `0.1.0`,
`darthjee/scripts:0.12.0`) and the npm publishing of the client packages. These docs are the
deployment reference.
New deployment requirements are written in GitHub issues and their plans, and these docs
are updated in the same change that ships a deployment change (see
[contributing.md](contributing.md#deployment-changes)).

| File | Content |
| --- | --- |
| [pipeline.md](deployment/pipeline.md) | CircleCI check jobs, CI database access and Codacy, deploy chain, tag filters, npm publish chain and its scripts, the `pidgey-deploy` and `pidgey-npm` contexts and their restrictions, `serial-group`, `SSH_KNOWN_HOSTS` guard, deploy-script actions and version, exclude list and what ships, logs, who can deploy and publish. |
| [variables.md](deployment/variables.md) | Every variable of the `pidgey-deploy` and `pidgey-npm` contexts, what it holds and its value format; the npm token settings, the deploy key and host key. |
| [server.md](deployment/server.md) | Host folders (live, staging, shared), the `config/.env` link, the web directory, the shared `.env`, host requirements, the database users and HTTPS. |
| [operations.md](deployment/operations.md) | Releasing, rerunning, rotating keys, recovering a broken swap and removing stale staging folders; publishing the first client release manually, setting up npm publishing, releasing, dry-running, deprecating and unpublishing the client packages, rotating the npm token. |
| [known-gaps.md](deployment/known-gaps.md) | What the current deployment and npm publishing do not cover yet, and where each gap is tracked. |
