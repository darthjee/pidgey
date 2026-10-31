# Single CI image version and deploy commands

1. **Image bump.** Move every `darthjee/circleci_pidgey-base:0.0.1` reference to `0.0.2`:
   - the `pidgey-base` executor;
   - the `backend_tests` primary image.

   Use a YAML anchor or the executor so the version is written once. `backend_tests` needs service containers, so an anchor on the image string, e.g. `ci_base_image: &ci_base_image darthjee/circleci_pidgey-base:0.0.2`, is the simplest way to share it.
2. **`deploy_setup` command**, run first in every context job:
   - Step "Check SSH_KNOWN_HOSTS": `if [ -z "${SSH_KNOWN_HOSTS:-}" ]; then echo "SSH_KNOWN_HOSTS is not set" >&2; exit 1; fi`
   - Step "Generate SSH key file": `deploy_frontend.sh generate_key_file`
3. **Semver filter anchor**, next to `all_tags`:

   ```yaml
   deploy_tags: &deploy_tags
     tags:
       only: /^\d+\.\d+\.\d+$/
     branches:
       ignore: /.*/
   ```

## Files to Change

- `.circleci/config.yml`: image anchor or executor at `0.0.2`, the `deploy_setup` command and the `deploy_tags` filter anchor.
