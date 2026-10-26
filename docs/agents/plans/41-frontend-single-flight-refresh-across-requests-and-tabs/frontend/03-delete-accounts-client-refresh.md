# Delete AccountsClient.refresh
`AccountsClient.refresh` has no live callers. It would bypass the single flight, and a `401` on it would make `handleUnauthorized` refresh the same token again. Delete it so `ApiClient` owns the only refresh path.

- Remove the `refresh(refreshToken)` method and its JSDoc from `frontend/assets/js/client/AccountsClient.js`.
- Update the object-level JSDoc ("registration, login, refresh, logout" and "Every method that receives a fresh refresh token...") so it no longer lists refresh.
- Remove the `.refresh` row from `tokenPairRows` in `frontend/specs/assets/js/client/AccountsClientSpec.js`.
- Before deleting, `grep -rn "AccountsClient.refresh\|client.refresh(" frontend/assets frontend/specs` to confirm there are no other callers. `AuthStatus.refresh` is unrelated (it calls `/auth/status.json`) and stays.

## Files to Change
- `frontend/assets/js/client/AccountsClient.js`: delete `refresh`; update JSDoc.
- `frontend/specs/assets/js/client/AccountsClientSpec.js`: delete the `.refresh` case.
