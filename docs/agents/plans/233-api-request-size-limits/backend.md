# Backend Plan: API request size limits

Main plan: [plan.md](plan.md)

## Shared contracts

- **`=?` rule:** a subject that is printable ASCII but contains `=?` is encoded as RFC 2047 encoded-words (`=?UTF-8?B?…?=`), through the same path as a non-ASCII subject. Every other printable-ASCII subject stays as-is and folded at 78 characters.
- The 255-character subject cap is a spec rule for the future `POST /v1/messages` handler. It is **not** implemented in this issue.

## Implementation Steps

### Step 1 — Encode printable subjects containing `=?`

In `MimeBuilder::subject()`, keep the printable-ASCII branch (`fold()`) only when the subject does not contain `=?`; otherwise use the encoded-words branch. Update the class docblock (the subject bullet) to state the rule.

### Step 2 — Tests

In `MimeBuilderTest`, add cases: a printable subject with `=?` (for example `Price =?100`) produces encoded-words that decode back to the original; a subject with a lone `=` or `?` stays as-is; an existing printable subject is unchanged. Keep 100% coverage of the changed branch.

## Files to Change

- `source/lib/Email/MimeBuilder.php` — route `=?` subjects through the encoded-words branch; docblock.
- `source/tests/unit/Email/MimeBuilderTest.php` — the new cases.

## CI Checks

- `source/`: `make tests` (`composer lint && composer tests`; CI jobs: `backend_checks`, `backend_tests`)

## Notes

- The test email (`TestEmail`) uses the same builder, so it follows the rule too.
- Do not add a subject length check to `MimeBuilder`: the cap is request validation, owned by the future handler.
