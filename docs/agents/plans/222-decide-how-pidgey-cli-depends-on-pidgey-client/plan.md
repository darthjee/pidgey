# Plan: Decide how pidgey-cli depends on pidgey-client

Issue: [222-decide-how-pidgey-cli-depends-on-pidgey-client.md](../../issues/222-decide-how-pidgey-cli-depends-on-pidgey-client.md)

## Overview

This issue changes specs only. It records the dependency decision of #222 in the Node client
specs and removes every `#222` pending marker:

- the caret range `^<floor>`, where the floor is derived from `clients/nodejs/package.json`;
- the staging-copy pack script, library-first release order and the registry check;
- the `1.0.0` behaviour;
- the fix-forward rule;
- `pidgey --version` printing both versions, through a new library `VERSION` export;
- the new smoke-test assertions and the committed-file lint.

See [product-owner.md](product-owner.md) for the full plan.
