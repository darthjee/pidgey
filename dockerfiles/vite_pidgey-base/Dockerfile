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
