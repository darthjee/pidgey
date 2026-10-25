ARG BASE_VERSION=0.0.1
ARG BASE_SUFFIX=""
FROM darthjee/scripts:0.8.0 AS scripts
FROM darthjee/vite_pidgey-base:${BASE_VERSION}${BASE_SUFFIX} AS base

COPY --chown=node:node \
  ./frontend/package.json frontend/yarn.lock \
  /home/node/app/

######################################

FROM base AS builder

ENV HOME_DIR=/home/node

USER root
COPY --chown=node:node --from=scripts /home/scripts/builder/yarn_builder.sh /usr/local/sbin/yarn_builder.sh
RUN /bin/bash yarn_builder.sh
USER node

#######################
# FINAL IMAGE
FROM base
ENV HOME_DIR=/home/node

COPY --chown=node:node --from=builder /home/node/yarn/new/ /usr/local/share/.cache/yarn/v6/

USER node
