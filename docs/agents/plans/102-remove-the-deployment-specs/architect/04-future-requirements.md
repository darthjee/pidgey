# Say where future deployment requirements go

Write down the decision from the issue: new deployment requirements are written in GitHub
issues and their plans, and the deployment docs are updated in the same change that ships the
deployment change. There is no deployment spec.

Write it in `docs/agents/contributing.md` (a short paragraph near the CI section or a
documentation-rules section) and in `docs/agents/deployment.md` (one line under its purpose).
The matching note in `docs/agents/specs.md` is the product-owner's (see
[product-owner.md](../product-owner.md)).

## Files to Change
- `docs/agents/contributing.md` — rule for deployment requirements.
- `docs/agents/deployment.md` — one-line statement that these docs are the reference and are updated when deployment changes ship.
