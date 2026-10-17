# Definition of Done

Purpose: define when a change is complete.

A change is done when:
1. Relevant specs were read, and updated first if the change alters a decision.
2. New behavior is covered by tests (unit, and integration where DB/SMTP is involved).
3. `make tests` is green locally (backend tests + lint, frontend tests + lint).
4. CI is green.
