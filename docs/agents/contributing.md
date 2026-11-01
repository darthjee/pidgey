# Contributing

Pidgey's backend is plain PHP 8.4 (no framework) and its admin UI is React (plain
JavaScript). The examples below are PHP unless they say otherwise. The specs under
[`docs/agents/specs.md`](specs.md) are the source of truth: when a change alters a
decision, update the spec first, in the same change set.

## Commit Guidelines

- **Atomic and Unitary:** Each commit must represent a single logical change.
  *Example:*
  - Good: `Add revoke() method to ApiTokenRepository`
  - Bad: `Add revoke() and refactor SmtpTransport error mapping`
- **No Unrelated Changes:** Do not mix unrelated changes in the same commit.
- **Separate Refactoring:** Whenever possible, separate refactoring commits from new feature or bugfix commits.
- **Format:** Follow [`.github/commit_message_template.md`](../../.github/commit_message_template.md).

## Pull Requests

- **Descriptive Summary:** Every PR must include a clear and descriptive summary of its purpose and changes (see [`.github/pull_request_template.md`](../../.github/pull_request_template.md)).
- **PR Description Files:** If a description cannot be provided directly in the PR, generate a file with the PR description (e.g., `docs/agents/issues/<pr_number>_description.md`), but do not commit this file.

## Definition of Done for PRs

A PR is considered complete when (see also [specs/testing/definition-of-done.md](specs/testing/definition-of-done.md)):

- The stated objective has been achieved.
- Relevant specs were read, and updated first if the change alters a decision.
- All tests are passing (unit, and integration where DB/SMTP is involved) — `make tests` is green.
- Linting passes without errors (`composer lint` for the backend, `yarn lint` for the frontend).
- Code coverage is as high as reasonably possible.
- Code is not overly complex:
  - Classes and methods should have clear, focused responsibilities.
  - If a class or method is taking on too many responsibilities, refactor to simplify.
  - Methods should be small and do exactly one thing. If a method is growing, extract parts into private helper methods or separate classes.
  - *Example (pseudo-code):*

    ```php
    // Good: Each method does one thing
    final class MessageSender
    {
        public function send(Message $message): SendOutcome { ... }
        public function resolveConnection(int $organizationId): SmtpConnection { ... }
    }

    // Bad: Method does too much
    final class MessageSender
    {
        public function send(Message $message): SendOutcome
        {
            $this->validate($message);
            $this->resolveConnection($message);
            $this->openSocket();
            $this->logAttempt();
            ...
        }
    }
    ```

  - This requirement applies primarily to source code. For tests, refactor only if there is excessive duplication.

### CI Checks

Before a PR is considered complete, all CI checks relevant to the modified parts of the project must pass locally.

To determine which checks to run:

1. Identify which top-level folders contain modified files.
2. Read `.circleci/config.yml` to find which jobs are triggered for those folders (look for path filters, `only` rules, or job names that reference those folders).
3. Run the corresponding local commands for each relevant job.

Jobs defined in `.circleci/config.yml` (check jobs run on every branch and tag); `build_images`,
still future work (see [deployment/known-gaps.md](deployment/known-gaps.md#no-automated-image-builds)),
is marked *(planned)*.
The deploy jobs run only on semver tags, after the check jobs pass, and have no local equivalent:

| Modified folder | Job | Local commands |
| --- | --- | --- |
| `source/` | `backend_checks` | `composer lint` (in the `tests` container) |
| `source/` | `backend_tests` | `composer db:prepare && composer coverage` (in the `tests` container) |
| `frontend/` | `frontend_checks` | `cd frontend && yarn lint && yarn test && yarn coverage` |
| `dockerfiles/` | `build_images` *(planned)* | `make build` (and `make build-base` / `make build-ci-base` when a base image changes) |
| — | `build_backend` | none (tag-triggered deploy, see [deployment/pipeline.md](deployment/pipeline.md)) |
| — | `build_frontend` | none (tag-triggered deploy) |
| — | `upload_backend` | none (tag-triggered deploy) |
| — | `upload_frontend` | none (tag-triggered deploy) |
| — | `release` | none (tag-triggered deploy) |

`make tests` runs everything inside the `tests` container.

Run only the checks that correspond to the folders you changed. If a new container or application folder is added in the future, its corresponding test and check jobs must be included before merging changes to that folder.

This same process must be followed when **planning how to resolve an issue**: include a final step in the plan that identifies the affected folders and lists the CI commands to run before opening a PR.

### Deployment changes

Deployment has no spec. New deployment requirements are written in the GitHub issue and its
plan (`docs/agents/plans/`). A change to the pipeline, the deploy variables, the host layout or
the runbooks updates [deployment.md](deployment.md) and `deployment/*.md` in the **same**
change that ships it, so those docs keep describing the deployment as built. A gap the change
leaves open goes in [deployment/known-gaps.md](deployment/known-gaps.md).

## Code Organization

### File Responsibility: Class Declarers vs Scripts

Every source file (excluding test files) must act as a **class declarer**: it should declare exactly one class, interface, trait or enum (as PSR-4 requires). Files must not act as **scripts**, meaning they must not execute logic or cause side effects when they are loaded.

The only exceptions are **entrypoints**:

| Application | Entrypoint |
| --- | --- |
| Backend HTTP (`source/`) | `source/public/index.php` (front controller) |
| Migrations | the migration script in `scripts/` |
| Frontend (`frontend/`) | `frontend/assets/js/main.jsx` |

*Example:*

```php
// Good: class declarer — declares a class, nothing runs on load
<?php

declare(strict_types=1);

namespace Pidgey\Http;

final class Router
{
    public function dispatch(Request $request): Response { ... }
}

// Bad: script — executes logic at file level
<?php

$router = new Router();
$router->get('/v1/messages', $handler);
return $router;
```

Test files are exempt from this rule and may execute setup code freely.

### File Naming: PascalCase for Class Files

Files that declare a class must use **PascalCase** naming, matching the class name exactly (PSR-4), and live in the directory matching their namespace.

*Examples:*

- `source/lib/Http/Router.php` for `Pidgey\Http\Router`
- `source/lib/Service/MessageSender.php` for `Pidgey\Service\MessageSender`
- `source/lib/Repository/ApiTokenRepository.php` for `Pidgey\Repository\ApiTokenRepository`

Test files mirror the source path with a `Test` suffix:

- `Router.php` → `source/tests/unit/Http/RouterTest.php`
- `ApiTokenRepository.php` → `source/tests/integration/Repository/ApiTokenRepositoryTest.php`

Frontend: component and class files use PascalCase (`ApiClient.js`, `App.jsx`), and their Jasmine specs mirror `assets/js/` under `frontend/specs/` (e.g. `specs/client/ApiClient_spec.js`). Non-class helpers in `utils/` use camelCase.

### Method Order: Public Before Private

Within a class, **public methods must be declared before protected and private methods**. Private methods serve as implementation helpers and should appear at the end of the class body.

*Example:*

```php
// Good: public methods first, private methods last
final class MigrationRunner
{
    public function run(): void
    {
        $this->ensureTable();
        $this->applyPending();
    }

    public function status(): array { ... }

    private function ensureTable(): void { ... }
    private function applyPending(): void { ... }
}

// Bad: private methods mixed in with or before public methods
final class MigrationRunner
{
    private function ensureTable(): void { ... }

    public function run(): void { ... }

    private function applyPending(): void { ... }
}
```

## Dependency Injection

Classes must receive their dependencies (data, configuration, collaborators such as `PDO` or a logger) as constructor arguments. A class must never reach out to load files, read environment variables, or fetch configuration on its own.

**Entrypoints are the only place responsible for loading configuration.** They do it through the `Pidgey\Infra` configuration loader, which reads environment variables. Wiring is manual, through a small container/factory class with no DI framework. The entrypoint then passes the loaded values down to the classes that need them.

This makes every class independently testable: tests simply instantiate the class with the data they need, without touching the filesystem or environment.

*Example:*

```php
// Good: class receives its collaborators — easy to test
final class ApiTokenRepository
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function findByHash(string $hash): ?ApiToken { ... }
}

// In source/public/index.php (entrypoint), via the container:
$config = Config::fromEnvironment();
$repository = new ApiTokenRepository(PdoFactory::create($config));

// Bad: class loads its own config — hard to test and couples to the environment
final class ApiTokenRepository
{
    public function findByHash(string $hash): ?ApiToken
    {
        $pdo = new \PDO(getenv('DATABASE_DSN')); // ❌
        ...
    }
}
```

This principle applies to all classes, including helpers and factories invoked from entrypoints. If a class needs data, it gets it through its constructor.

## Refactoring Guidelines

When refactoring, aim to:

- **Reduce Code Duplication:**
  *Example:* Move repeated setup code in tests to a factory method.

  ```php
  // Good
  private function buildOrganization(array $attrs = []): Organization
  {
      return new Organization(...array_merge(['id' => 1, 'name' => 'Acme'], $attrs));
  }
  // In tests:
  $organization = $this->buildOrganization(['id' => 2]);

  // Bad
  $organization = new Organization(id: 2, name: 'Acme');
  // ...repeated in many files
  ```
