# Add the pipeline pending decisions to known gaps

Add the two pending decisions from `docs/agents/specs/deployment/pipeline.md` to
`docs/agents/deployment/known-gaps.md`, in the existing gap style (what, why it's open, where
it's tracked):

- **Frontend coverage not in Codacy**: open question whether `frontend_checks` uploads
  frontend coverage (making the backend upload a partial report plus a final step) or frontend
  coverage stays out of Codacy.
- **No automated image builds**: `build_images` is future work; images are built and pushed by
  hand.

Also check that `No proxy rate limiting` covers everything in `specs/deployment/production.md`
and in the `security.md` known gap (per-IP rate limit on `/auth/*.json`, the shared host can't
do it, `USER_APPROVAL_REQUIRED=true`). The application-level throttle decision stays owned by
`specs/api/pending.md`.

## Files to Change
- `docs/agents/deployment/known-gaps.md` — two new gap sections; check the rate-limit gap is complete.
- `docs/agents/deployment.md` — update the `known-gaps.md` index row if its description lists the gaps.
