# Plan: Raise the HSTS max-age to one day

Issue: [115-raise-the-hsts-max-age-to-one-year.md](../../issues/115-raise-the-hsts-max-age-to-one-year.md)

## Overview
Raise `Strict-Transport-Security` from `max-age=300` to `max-age=86400` (one day) in `source/public/.htaccess`, and bring the deployment docs and spec in line with the new value. One year is left for a later step.

See [infra.md](infra.md) for the full plan.
