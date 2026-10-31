# Plan: CI images for deploy

Issue: [98-ci-images-for-deploy.md](../../issues/98-ci-images-for-deploy.md)

## Overview

Add the deploy tooling (`deploy_frontend.sh` from `darthjee/scripts:0.11.0`, `openssh-client`, `rsync`) to the `circleci_pidgey-base` CI image and bump its version to `0.0.2`, so the deploy context jobs of sub-issue 4 can run in it. No frontend deploy image is added. `.circleci/config.yml` is out of scope (sub-issue 4).

## Agents involved

- [infra](infra.md)
- [product-owner](product-owner.md)

## Shared contracts

- **Image:** `darthjee/circleci_pidgey-base`, new tag `0.0.2` (`Makefile` `CI_BASE_VERSION=0.0.2`), amd64 only.
- **Script:** `deploy_frontend.sh` copied from `darthjee/scripts:0.11.0` (`/home/scripts/sbin/deploy_frontend.sh`) to `/usr/local/sbin/deploy_frontend.sh`. `0.12.0` (`cleanup`) is not released yet; the upgrade is a later version bump.
- **Packages:** `openssh-client` and `rsync` installed through `apt-get`; `bash` comes with `cimg/php`.
- **No frontend deploy image** and no `.circleci/config.yml` change in this issue.
