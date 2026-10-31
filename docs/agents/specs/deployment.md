# Deployment

Purpose: index of the specs describing CI and the production deployment.

| File | Content |
| --- | --- |
| [pipeline.md](deployment/pipeline.md) | CircleCI check jobs and deploy chain (build jobs without the deploy context, context jobs with it), their images, filters, context restriction and order, and the workflow. |
| [release.md](deployment/release.md) | Step-by-step release flow: staging folder, backend and frontend builds and uploads, `.env` link, `check-config`, `migrate`, swap and cleanup; the deploy script and the CircleCI variables. |
| [server.md](deployment/server.md) | Production host: live, staging and shared folders, the `config/.env` link, the web directory, host requirements and the *to confirm* checks. |
| [security.md](deployment/security.md) | Deployment security decisions: host keys, deploy key, CI context and its restriction, third-party code isolation, tag ruleset, `.env` permissions, what ships, logs, known gaps. |
| [edge-cases.md](deployment/edge-cases.md) | Failure modes of the deployment and how each is handled, including manual swap recovery. |
| [production.md](deployment/production.md) | Production recommendations not enforced by the app (proxy rate limiting). |
