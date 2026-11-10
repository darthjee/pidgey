<?php

/**
 * Holds an organization row lock, for the concurrent `activate` / `deactivate` tests.
 *
 * Usage: php organization-lock-holder.php <organization id> <hold milliseconds> [<connection id>]
 *
 * Connects with the test environment it inherits (`DB_*`), opens a transaction, locks the
 * organization row (`SELECT … FROM organizations WHERE id = ? FOR UPDATE`) and prints `LOCKED`
 * once the lock is held. It then holds the lock for the given time, activates the connection
 * when one is given (deactivating every other connection of the organization), commits and
 * exits 0. Any failure is written on stderr and exits 1.
 */

declare(strict_types=1);

use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\PdoFactory;
use Pidgey\Repository\OrganizationRepository;
use Pidgey\Repository\SmtpConnectionRepository;

require __DIR__ . '/../../../vendor/autoload.php';

/**
 * Writes the message on stderr and exits 1.
 */
function abortLockHolder(string $message): never
{
    fwrite(STDERR, "organization-lock-holder: {$message}\n");

    exit(1);
}

/**
 * Parses a positive integer argument.
 */
function positiveArgument(string $value, string $name): int
{
    if (preg_match('/^[1-9][0-9]*$/', $value) !== 1) {
        abortLockHolder("{$name} must be a positive integer");
    }

    return (int) $value;
}

if ($argc < 3 || $argc > 4) {
    abortLockHolder('usage: organization-lock-holder.php <organization id> <hold ms> [<connection id>]');
}

$organizationId = positiveArgument($argv[1], 'organization id');
$holdMilliseconds = positiveArgument($argv[2], 'hold ms');
$connectionId = $argc === 4 ? positiveArgument($argv[3], 'connection id') : null;

try {
    $pdo = (new PdoFactory())->create(DatabaseConfig::fromEnv());
    $pdo->beginTransaction();

    if (!(new OrganizationRepository($pdo))->lockForUpdate($organizationId)) {
        $pdo->rollBack();
        abortLockHolder("no organization {$organizationId}");
    }

    fwrite(STDOUT, "LOCKED\n");
    fflush(STDOUT);
    usleep($holdMilliseconds * 1000);

    if ($connectionId !== null) {
        (new SmtpConnectionRepository($pdo))->activate($organizationId, $connectionId);
    }

    $pdo->commit();
} catch (Throwable $exception) {
    abortLockHolder($exception::class . ': ' . $exception->getMessage());
}

exit(0);
