<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Http\Handler;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Pidgey\Http\Handler\SmtpConnectionActivateHandler;
use Pidgey\Http\Handler\SmtpConnectionDeactivateHandler;
use Pidgey\Http\Handler\SmtpConnectionHandler;
use Pidgey\Http\Handler\SmtpConnectionStateHandler;
use Pidgey\Http\OperatorLog;
use Pidgey\Http\SmtpConnectionRules;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\PdoFactory;
use Pidgey\Repository\OrganizationRepository;
use Pidgey\Repository\SmtpConnectionRepository;
use Pidgey\Tests\Support\LockHookPdo;
use Pidgey\Tests\Support\SmtpConnectionEndpointTestCase;

/**
 * Concurrent activations, deactivations and updates of SMTP connections, against the real
 * database.
 *
 * The organization lock is held by a child process (`organization-lock-holder.php`) with real
 * waits and loose bounds; the update races run deterministically through LockHookPdo. The
 * child is reaped in teardown, its exit status and empty stderr asserted.
 */
#[CoversClass(SmtpConnectionActivateHandler::class)]
#[CoversClass(SmtpConnectionDeactivateHandler::class)]
#[CoversClass(SmtpConnectionHandler::class)]
#[CoversClass(SmtpConnectionStateHandler::class)]
#[CoversClass(OrganizationRepository::class)]
#[CoversClass(SmtpConnectionRepository::class)]
final class SmtpConnectionConcurrencyTest extends SmtpConnectionEndpointTestCase
{
    private const LOCK_HOLDER = __DIR__ . '/../../../fixtures/scripts/organization-lock-holder.php';

    private const HOLD_MILLISECONDS = 1000;

    /** The parent must have waited at least this share of the hold time. */
    private const MIN_WAIT_SECONDS = self::HOLD_MILLISECONDS / 1000 * 0.5;

    private const LOCKED_TIMEOUT_SECONDS = 10;

    /** @var resource|null */
    private $child = null;

    /** @var array<int, resource> */
    private array $childPipes = [];

    protected function tearDown(): void
    {
        try {
            if ($this->child !== null) {
                [$exitCode, $stdout, $stderr] = $this->reapChild();

                $this->assertSame('', $stderr, 'organization-lock-holder: stderr is not empty');
                $this->assertSame(0, $exitCode, 'organization-lock-holder: exit code');
                $this->assertSame('', $stdout, 'organization-lock-holder: unexpected output');
            }
        } finally {
            parent::tearDown();
        }
    }

    public function testActivateWaitsForTheLockHolderAndWins(): void
    {
        $user = $this->account();
        $organizationId = $user->organizationId;
        $first = $this->fixtures->smtpConnection(organizationId: $organizationId);
        $second = $this->fixtures->smtpConnection(organizationId: $organizationId);

        $this->startLockHolder($organizationId, $first);
        $started = microtime(true);
        $response = $this->send($user, 'POST', "/smtp-connections/{$second}/activate.json");
        $waited = microtime(true) - $started;

        $this->assertSame(200, $response->status(), $response->body());
        $this->assertTrue(self::item($response)['active']);
        $this->assertGreaterThanOrEqual(self::MIN_WAIT_SECONDS, $waited);
        $this->assertSame([$second], $this->activeIds($organizationId));
    }

    public function testDeactivateAfterTheLockHolderLeavesNoneActive(): void
    {
        $user = $this->account();
        $organizationId = $user->organizationId;
        $first = $this->fixtures->smtpConnection(organizationId: $organizationId);
        $this->fixtures->smtpConnection(organizationId: $organizationId);

        $this->startLockHolder($organizationId, $first);
        $started = microtime(true);
        $response = $this->send($user, 'POST', "/smtp-connections/{$first}/deactivate.json");
        $waited = microtime(true) - $started;

        $this->assertSame(200, $response->status(), $response->body());
        $this->assertFalse(self::item($response)['active']);
        $this->assertGreaterThanOrEqual(self::MIN_WAIT_SECONDS, $waited);
        $this->assertSame([], $this->activeIds($organizationId));
    }

    public function testAnActivationRacingAnUpdateStands(): void
    {
        $user = $this->account();
        $organizationId = $user->organizationId;
        $updated = $this->fixtures->smtpConnection(organizationId: $organizationId, active: true);
        $other = $this->fixtures->smtpConnection(organizationId: $organizationId);
        $hooked = $this->lockHookPdo();
        $hooked->beforeNextLock(function () use ($user, $other): void {
            // Another request activates the other connection between the read and the row lock.
            $activation = $this->send($user, 'POST', "/smtp-connections/{$other}/activate.json");
            $this->assertSame(200, $activation->status(), $activation->body());
        });

        $response = $this->send(
            $user,
            'PATCH',
            "/smtp-connections/{$updated}.json",
            ['name' => 'renamed during an activation'],
            [],
            static fn (): PDO => $hooked,
        );

        $this->assertSame(200, $response->status(), $response->body());
        $this->assertSame('renamed during an activation', self::item($response)['name']);
        $this->assertFalse(self::item($response)['active']);
        $this->assertSame([$other], $this->activeIds($organizationId));
    }

    public function testAnActivationOfTheUpdatedConnectionStands(): void
    {
        $user = $this->account();
        $organizationId = $user->organizationId;
        $updated = $this->fixtures->smtpConnection(organizationId: $organizationId);
        $hooked = $this->lockHookPdo();
        $hooked->beforeNextLock(function () use ($user, $updated): void {
            $activation = $this->send($user, 'POST', "/smtp-connections/{$updated}/activate.json");
            $this->assertSame(200, $activation->status(), $activation->body());
        });

        $response = $this->send(
            $user,
            'PATCH',
            "/smtp-connections/{$updated}.json",
            ['port' => 2525],
            [],
            static fn (): PDO => $hooked,
        );

        $this->assertSame(200, $response->status(), $response->body());
        $this->assertTrue(self::item($response)['active']);
        $this->assertSame(2525, self::item($response)['port']);
        $this->assertSame([$updated], $this->activeIds($organizationId));
    }

    public function testTheSecondUpdateValidatesTheStateLeftByTheFirst(): void
    {
        $user = $this->account();
        $organizationId = $user->organizationId;
        $id = $this->fixtures->smtpConnection(organizationId: $organizationId, encryption: 'tls');
        $hooked = $this->lockHookPdo();
        $hooked->beforeNextLock(function () use ($user, $id, $hooked): void {
            // The later update has already read a snapshot without the credentials...
            $hooked->query('SELECT COUNT(*) FROM smtp_connections')->fetchColumn();
            // ...when the earlier one adds them and commits.
            $first = $this->send($user, 'PATCH', "/smtp-connections/{$id}.json", [
                'username' => 'mailer',
                'password' => self::$canary,
            ]);
            $this->assertSame(200, $first->status(), $first->body());
        });

        $response = $this->send(
            $user,
            'PATCH',
            "/smtp-connections/{$id}.json",
            ['encryption' => 'none'],
            [],
            static fn (): PDO => $hooked,
        );

        $this->assertValidationFailed(
            $response,
            [SmtpConnectionRules::ENCRYPTION_REQUIRED, SmtpConnectionRules::PASSWORD_REQUIRED],
        );
        $row = $this->row('smtp_connections', 'id', $id);
        $this->assertSame('tls', $row['encryption']);
        $this->assertSame('mailer', $row['username']);
        $this->assertNotNull($row['password']);
    }

    /**
     * @return array<string, array{string, boolean}>
     */
    public static function stateChanges(): array
    {
        return [
            'activate' => ['activate', false],
            'deactivate' => ['deactivate', true],
        ];
    }

    #[DataProvider('stateChanges')]
    public function testAStateChangeRacingADeleteAnswers404WithoutALogLine(string $action, bool $active): void
    {
        $user = $this->account();
        $organizationId = $user->organizationId;
        $id = $this->fixtures->smtpConnection(organizationId: $organizationId, active: $active);
        $hooked = $this->lockHookPdo();
        $hooked->beforeNextLock(function () use ($user, $id, $hooked): void {
            // The state change has already read a snapshot that still holds the connection...
            $hooked->query('SELECT COUNT(*) FROM smtp_connections')->fetchColumn();
            // ...when a delete commits.
            $delete = $this->send($user, 'DELETE', "/smtp-connections/{$id}.json");
            $this->assertSame(204, $delete->status(), $delete->body());
        });

        $response = $this->send(
            $user,
            'POST',
            "/smtp-connections/{$id}/{$action}.json",
            [],
            [],
            static fn (): PDO => $hooked,
        );

        $this->assertError($response, 404, 'NOT_FOUND', 'SMTP connection not found');
        $this->assertSame(0, $this->rowCount('smtp_connections'));
        $this->assertSame(
            [OperatorLog::SMTP_CONNECTION_DELETE],
            array_column($this->operatorLines(), 0),
        );
    }

    private function lockHookPdo(): LockHookPdo
    {
        $config = DatabaseConfig::fromEnv();
        $factory = new PdoFactory();

        return new LockHookPdo($factory->dsn($config, true), $config->user, $config->password, $factory->options());
    }

    /**
     * Starts the lock holder on the organization, activating the connection before it commits,
     * and waits until it holds the lock.
     */
    private function startLockHolder(int $organizationId, ?int $connectionId = null): void
    {
        $arguments = [PHP_BINARY, self::LOCK_HOLDER, (string) $organizationId, (string) self::HOLD_MILLISECONDS];

        if ($connectionId !== null) {
            $arguments[] = (string) $connectionId;
        }

        $process = proc_open($arguments, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);
        fclose($pipes[0]);
        $this->child = $process;
        $this->childPipes = [1 => $pipes[1], 2 => $pipes[2]];

        stream_set_timeout($pipes[1], self::LOCKED_TIMEOUT_SECONDS);
        $line = fgets($pipes[1]);

        $this->assertSame("LOCKED\n", $line, 'organization-lock-holder: did not lock');
    }

    /**
     * Waits for the child to exit and returns its exit code, remaining stdout and stderr.
     *
     * @return array{int, string, string}
     */
    private function reapChild(): array
    {
        $stdout = (string) stream_get_contents($this->childPipes[1]);
        $stderr = (string) stream_get_contents($this->childPipes[2]);
        fclose($this->childPipes[1]);
        fclose($this->childPipes[2]);
        $exitCode = proc_close($this->child);
        $this->child = null;
        $this->childPipes = [];

        return [$exitCode, $stdout, $stderr];
    }
}
