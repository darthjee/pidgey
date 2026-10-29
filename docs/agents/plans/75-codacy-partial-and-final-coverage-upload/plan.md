# Plan: Codacy partial and final coverage upload

Issue: [75-codacy-partial-and-final-coverage-upload.md](../../issues/75-codacy-partial-and-final-coverage-upload.md)

## Overview
Switch CircleCI to Codacy partial coverage uploads. `backend_tests` (Clover) and `frontend_checks` (lcov) each send a partial report through a new reusable `upload_codacy_coverage` command, which first rewrites the report's file paths to be repo-relative. A new `coverage_final` job then finalizes the commit. The frontend `c8` config is widened (`all` + `include`) so untested files under `assets/` count.

## Agents involved

- [frontend](frontend.md)
- [infra](infra.md)

## Shared contracts

- `npm run coverage` (run from the flattened `frontend/` root) keeps writing lcov to `coverage/lcov.info`. With the c8 change it also lists `assets/**` files that no spec loads (0% coverage).
- `composer coverage` (run from the flattened `source/` root) keeps writing Clover to `coverage/clover.xml`. This plan doesn't change it.
- The infra upload command rewrites paths so Codacy receives `source/lib/...` (Clover `<file name>`) and `SF:frontend/assets/...` (lcov). The frontend change must not move or rename the report.
