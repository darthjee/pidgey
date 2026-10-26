# Delete RegisterController
`RegisterController` is used by no page or route; only its spec references it. Delete both files and check that nothing else imports them (`grep -rn RegisterController frontend/assets frontend/specs` returns nothing afterwards). Remove the now-empty `controllers` folder entries only if the folder becomes empty (it does not: `MyAccountController` and `AuthorizationRequestsController` stay).

Run `npm run lint` and `yarn test` (or `npm run coverage`) in `frontend/` and keep both green.

## Files to Change
- `frontend/assets/js/components/resources/accounts/pages/controllers/RegisterController.js` — delete.
- `frontend/specs/assets/js/components/resources/accounts/pages/controllers/RegisterControllerSpec.js` — delete.
