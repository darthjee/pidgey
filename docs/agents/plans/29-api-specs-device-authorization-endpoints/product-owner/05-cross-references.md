# Cross-references

Replace every remaining "#29" placeholder and stale pointer with links to the new spec:

- `api/authentication/gate.md`: the shared `user` payload sentence ("...and in the device
  authorization spec (#29)") links `endpoints/auth/device-authorization/requester.md`.
- `overview/terminology.md`: "Authorization request" links
  `api/endpoints/auth/device-authorization.md`.
- `organizations/data-model/authorization-requests.md`: "defined in api/authentication.md
  (#27) and the device-authorization endpoint specs (#29)" links the new index; no column
  change.

Finish with `grep -rn "#29" docs/agents/specs` returning nothing, and check every new
relative link resolves.

## Files to Change
- `docs/agents/specs/api/authentication/gate.md` — link.
- `docs/agents/specs/overview/terminology.md` — link.
- `docs/agents/specs/organizations/data-model/authorization-requests.md` — link.
