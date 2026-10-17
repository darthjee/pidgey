# Frontend Build

Purpose: define how the frontend is built and served.

- `yarn build` (Vite) produces `frontend/dist/`.
- In development, `dist/` is mounted into the Apache web root as `static/`
  (see [docker/volumes.md](../docker/volumes.md)); `frontend_dev` serves hot-reload on :8030.
- In production, `dist/` is uploaded to the web root during deploy
  (see [deployment](../deployment.md)) and served as static files by Apache, same origin as the API.

**Pending decision:** whether the production image bakes `dist/` in, or the deploy
uploads it separately (majora pattern); see [deployment/pipeline.md](../deployment/pipeline.md).
