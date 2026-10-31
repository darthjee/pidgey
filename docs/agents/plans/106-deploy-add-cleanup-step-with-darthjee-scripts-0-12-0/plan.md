# Plan: Deploy: add cleanup step with darthjee/scripts 0.12.0

Issue: [106-deploy-add-cleanup-step-with-darthjee-scripts-0-12-0.md](../../issues/106-deploy-add-cleanup-step-with-darthjee-scripts-0-12-0.md)

## Overview
Move the CI image to `darthjee/scripts:0.12.0` and add `deploy_frontend.sh cleanup` as the last step of the `release` job, then update the specs and the deployment docs that describe `cleanup` as missing. The whole plan is owned by `infra`. It is blocked until `darthjee/scripts:0.12.0` is published (darthjee/docker#244).

See [infra.md](infra.md) for the full plan.
