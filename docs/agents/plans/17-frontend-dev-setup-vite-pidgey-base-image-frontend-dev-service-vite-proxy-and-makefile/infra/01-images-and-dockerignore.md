# Frontend images and .dockerignore

Add the published base image and make the derived image arm64-aware. Both use the **repository root** as their build context.

1. Create `dockerfiles/vite_pidgey-base/Dockerfile`:
   ```dockerfile
   FROM darthjee/scripts:0.8.0 AS scripts
   FROM darthjee/node:0.2.1 AS base
   USER node

   FROM base AS builder
   ENV HOME_DIR=/home/node
   COPY --chown=node:node frontend/package.json frontend/yarn.lock /home/node/app/
   USER root
   COPY --chown=node:node --from=scripts /home/scripts/builder/yarn_builder.sh /usr/local/sbin/yarn_builder.sh
   RUN /bin/bash yarn_builder.sh

   FROM base
   ENV HOME_DIR=/home/node
   COPY --chown=node:node --from=builder /home/node/yarn/new/ /usr/local/share/.cache/yarn/v6/
   USER node
   CMD ["npm", "run", "server"]
   ```
2. In `dockerfiles/vite_pidgey/Dockerfile`, keep the existing second yarn warm-up stage. Change the header to:
   ```dockerfile
   ARG BASE_VERSION=0.0.1
   ARG BASE_SUFFIX=""
   FROM darthjee/scripts:0.8.0 AS scripts
   FROM darthjee/vite_pidgey-base:${BASE_VERSION}${BASE_SUFFIX} AS base
   ```
   While editing, switch the `as` keywords to uppercase `AS` and `ENV HOME_DIR /home/node` to `ENV HOME_DIR=/home/node`, to avoid buildkit warnings.
3. Create a root `.dockerignore` listing `.git`, `docker_volumes/`, `frontend/node_modules`, `frontend/dist`, `frontend/coverage`, `frontend/.nyc_output`, `source/vendor` and `source/coverage`. The `dev_pidgey*` and `circleci_pidgey-base` builds use the `source/` context, so they don't read this file.

## Files to Change

- `dockerfiles/vite_pidgey-base/Dockerfile` — new base image.
- `dockerfiles/vite_pidgey/Dockerfile` — `BASE_SUFFIX` in `FROM`, `BASE_VERSION` default `0.0.1`.
- `.dockerignore` — new; keeps the repo-root context small and avoids reading mysql-owned files.
