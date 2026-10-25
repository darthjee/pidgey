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

Endpoint specs list the rules for each field; the messages always follow these templates.

**Pending decision:** message templates for rules not listed above (e.g. numeric ranges,
booleans, port numbers), to be added when an endpoint spec first needs one.
