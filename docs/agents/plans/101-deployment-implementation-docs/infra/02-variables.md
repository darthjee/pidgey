# Variables
Create `docs/agents/deployment/variables.md`. Write one table of every variable in the `pidgey-deploy` context: `SSH_HOST`, `SSH_PORT`, `SSH_USER`, `SSH_PRIVATE_KEY`, `SSH_KNOWN_HOSTS`, `SSH_REMOTE_DIR`, `SSH_REMOTE_TEMP_DIR`, `REMOTE_HOME`, `SHARED_DIR` and `PHP_BIN`. For each one, give what it holds and its format, and never a real value. Check the list against `.circleci/config.yml` and the `deploy_frontend.sh` actions it uses.

The table includes these #100 findings:
- `SSH_PRIVATE_KEY` is one line, with each newline escaped as a literal `\n`. The script turns them back into newlines. Give the command that produces it: `awk 'NR>1{printf "\\n"}{printf "%s",$0}' <key> | pbcopy`;
- `SSH_KNOWN_HOSTS` is a single ED25519 line from `ssh-keyscan -t ed25519`, and its fingerprint is checked against the host's before saving;
- the variables live only in the context and are not set as project variables, and secrets for forked PRs stay off.

State that app settings (`SECRET_KEY`, `DB_*`, …) are **not** CircleCI variables, and link to the server file's shared `.env` section.

## Files to Change
- `docs/agents/deployment/variables.md` — new.
