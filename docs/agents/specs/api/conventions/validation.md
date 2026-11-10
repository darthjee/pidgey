# Validation

Purpose: define how request bodies are validated and how validation messages are worded.

## Rules

- Validation runs on the request body **before** any business logic or side effect.
- **Unknown fields are silently dropped**, not rejected. For example, the admin frontend
  sends `password_confirmation` on reset-password, and the backend ignores it.
- Every field error is collected and reported **at once**, in a single response.
- Guards run **before** validation (see the guard order in [format.md](format.md)): an
  anonymous request to a protected route with an invalid body gets `401`, not `400`.
- Validation failures answer **`400 VALIDATION_FAILED`**, with one `details` entry per failed
  rule (envelope in [errors.md](errors.md)). This replaces the former
  `422 validation_error`.

## Field evaluation

Each declared field is evaluated on its own:

1. **Absent or `null`** → `<field> should not be empty`. No further rule runs for that field.
2. **Wrong type** (a number, array, object or boolean for a string field) →
   `<field> must be a string`. No further rule runs for that field.
3. **Trimming**, when the field opts in (see
   [endpoints/auth.md](../endpoints/auth.md#trimming)). Passwords and tokens are never
   trimmed.
4. **Not empty** is checked **after trimming**: `""` (or whitespace only, for a trimmed field)
   → `<field> should not be empty`. No further rule runs for that field.
5. Otherwise **every** other rule declared for the field runs, and each failure adds its
   message.

So a missing, `null`, empty or wrong-type field gets **exactly one** message, while a field that
passes those checks gets every failed rule. `details` lists fields in the order the endpoint
declares them, then each field's messages in the order its rules are declared.

### Rule semantics

- **Email:** the trimmed value must pass PHP's `filter_var($value, FILTER_VALIDATE_EMAIL)`.
  IDN and Unicode local parts are not accepted in v1. The max-length rule is a separate rule
  with its own message.
- **Lengths:** "N characters" counts **UTF-8 code points** (`mb_strlen`); "N bytes" counts
  **bytes** (`strlen`).
- **Invalid UTF-8** never reaches validation: the JSON body parser already rejects it with
  `400 BAD_REQUEST` ([request-handler/request-response/request.md](../../request-handler/request-response/request.md#json-body)).
- **Validated output:** only the declared fields are kept (with their trimmed values), which is
  how unknown fields are dropped.

## Message templates

`<field>` is the `camelCase` property name.

| Rule | Message |
| --- | --- |
| must be a string | `<field> must be a string` |
| not empty | `<field> should not be empty` |
| email | `<field> must be an email` |
| min length N | `<field> must be longer than or equal to N characters` |
| max length N | `<field> must be shorter than or equal to N characters` |
| max byte length N (UTF-8) | `<field> must be shorter than or equal to N bytes` |
| no NUL byte | `<field> must not contain a NUL byte` |
| one of a set | `<field> must be one of the following values: a, b, c` |
| integer (a JSON integer; not a float, even with no fraction, a string or a boolean) | `<field> must be an integer` |
| integer range `min`–`max` | `<field> must be between <min> and <max>` |
| hostname or IP literal | `<field> must be a hostname or an IP address` |
| no control characters | `<field> must not contain control characters` |
| no line breaks (CR or LF) | `<field> must not contain line breaks` |

Endpoint specs list the rules for each field; the messages always follow these templates.

In the range template, `<min>` and `<max>` are the bounds as decimal integers, e.g.
`port must be between 1 and 65535`. Only a JSON integer passes the integer rule: a
wrong-type integer field — a string such as `"587"`, a float, **including one with no
fraction** such as `587.0`, or a boolean — gets `<field> must be an integer` and no further
rule runs for it.

**Pending decision:** message templates for rules not listed above (e.g. booleans), to be
added when an endpoint spec first needs one.
