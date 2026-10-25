# frontend_dev compose service

Add the Vite dev service to `docker-compose.yml`. Don't use the `&base` anchor.

```yaml
  frontend_dev:
    image: darthjee/vite_pidgey
    build:
      context: .
      dockerfile: dockerfiles/vite_pidgey/Dockerfile
      args:
        BASE_VERSION: ${FE_BASE_VERSION:-0.0.1}
        BASE_SUFFIX: ${BASE_SUFFIX:-}
    working_dir: /home/node/app
    volumes:
      - ./frontend:/home/node/app
      - ./docker_volumes/node_modules:/home/node/app/node_modules
    ports:
      - "${FRONTEND_PORT:-8030}:8080"
    depends_on:
      - app
    command: yarn server
```

Add an empty `docker_volumes/node_modules/.gitkeep`. The existing `.gitignore` pattern `!docker_volumes/*/.gitkeep` already lets git track it. The file makes the folder belong to the host user instead of being created by Docker as root.

## Files to Change

- `docker-compose.yml` — new `frontend_dev` service.
- `docker_volumes/node_modules/.gitkeep` — new placeholder.
