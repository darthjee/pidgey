# Frontend Plan: CircleCI deploy workflow

Main plan: [plan.md](plan.md)

## Shared contracts

- Create `frontend/public/.htaccess`. Vite copies `public/` into `dist/` as-is, so it ships at `public/admin/.htaccess` in the release, via the `upload_frontend` job.

## Implementation Steps

### Step 1 — Add the admin no-cache `.htaccess`

Create `frontend/public/.htaccess` (Vite's default `publicDir`, since the project root is `frontend/`):

```apache
<IfModule mod_headers.c>
  <Files "index.html">
    Header set Cache-Control "no-cache"
  </Files>
</IfModule>
```

- Only `index.html` gets `no-cache`. Hashed assets under `assets/` keep Apache's default caching (spec: `docs/agents/specs/frontend/build.md#admin-serving`).
- `<IfModule>` keeps Apache from failing when `mod_headers` is off. The dev image (`dev_pidgey-base`) enables only `rewrite`.
- Run `yarn build` (or `npm run build`) inside the frontend container and check that `dist/.htaccess` exists. This confirms Vite copies the dotfile.

## Files to Change

- `frontend/public/.htaccess`: new file; the `index.html` no-cache header.

## CI Checks

- `frontend`: `npm run lint` and `npm run coverage` (CI job: `frontend_checks`). They should be unaffected.

## Notes

- `source/public/.htaccess` already 404s any dotfile path (`RewriteRule (^|/)\. - [R=404,L]`), so `/admin/.htaccess` itself is not served.
- `frontend/eslint.config.mjs` and the Jasmine specs don't scan `public/`. Confirm lint still passes.
