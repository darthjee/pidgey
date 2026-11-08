<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Infra\Cli;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Container;
use Pidgey\Crypto\DecryptionFailed;
use Pidgey\Crypto\EncryptionKeys;
use Pidgey\Crypto\PasswordCipher;
use Pidgey\Infra\Cli\ReencryptSmtpPasswordsCommand;
use Pidgey\Infra\ConnectionFactory;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\PdoFactory;
use Pidgey\Infra\Transaction;
use Pidgey\Repository\SmtpConnectionRepository;
use Pidgey\Tests\Support\DatabaseCleaner;
use Pidgey\Tests\Support\Fixtures;
use Pidgey\Tests\Support\IntegrationTestCase;
use Pidgey\Tests\Support\LockHookPdo;

/**
 * Runs without an outer test transaction, because the command opens one per row; every table is
 * emptied before and after each test instead.
 */
#[CoversClass(ReencryptSmtpPasswordsCommand::class)]
#[CoversClass(SmtpConnectionRepository::class)]
#[UsesClass(Container::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(DecryptionFailed::class)]
#[UsesClass(EncryptionKeys::class)]
#[UsesClass(Env::class)]
#[UsesClass(PasswordCipher::class)]
#[UsesClass(PdoFactory::class)]
#[UsesClass(Transaction::class)]
final class ReencryptSmtpPasswordsCommandTest extends IntegrationTestCase
{
    private const SOURCE_ROOT = __DIR__ . '/../../../..';

    private const OLD_KEY = 'reencrypt-test-smtp-encryption-key-old-0123456789';
    private const NEW_KEY = 'reencrypt-test-smtp-encryption-key-new-0123456789';
    private const LOST_KEY = 'reencrypt-test-smtp-encryption-key-lost-0123456789';

    private const PASSWORD = 'distinctive-rotation-password-5e8d1b';
    private const UPDATED_AT = '2020-01-02 03:04:05';

    private const VARIABLES = ['SMTP_ENCRYPTION_KEY', 'PREVIOUS_SMTP_ENCRYPTION_KEYS'];

    private PDO $pdo;

    private Fixtures $fixtures;

    /** @var array<string, string|false> */
    private array $saved = [];

    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::VARIABLES as $name) {
            $this->saved[$name] = getenv($name);
        }

        putenv('SMTP_ENCRYPTION_KEY=' . self::NEW_KEY);
        putenv('PREVIOUS_SMTP_ENCRYPTION_KEYS=' . self::OLD_KEY);

        $this->pdo = self::pdo();
        DatabaseCleaner::truncateAll($this->pdo);
        $this->fixtures = new Fixtures($this->pdo);
        $this->stdout = fopen('php://memory', 'w+');
        $this->stderr = fopen('php://memory', 'w+');
    }

    protected function tearDown(): void
    {
        DatabaseCleaner::truncateAll($this->pdo);
        fclose($this->stdout);
        fclose($this->stderr);

        foreach ($this->saved as $name => $value) {
            putenv($value === false ? $name : "{$name}={$value}");
        }

        parent::tearDown();
    }

    public function testReencryptsOldKeyRowsWithTheCurrentKey(): void
    {
        $organizationId = $this->fixtures->organization();
        $old = $this->connection($organizationId, self::OLD_KEY);
        $current = $this->connection($organizationId, self::NEW_KEY);
        $currentBytes = $this->storedPassword($current);
        $other = $this->connection($this->fixtures->organization(), self::OLD_KEY);
        $this->fixtures->smtpConnection(organizationId: $organizationId);

        $this->assertSame(0, $this->command()->run([]));

        $this->assertOutput("re-encrypted: 2\nalready current: 1\nunreadable: 0\n");
        $newOnly = new PasswordCipher(new EncryptionKeys(self::NEW_KEY));

        foreach ([$old, $other] as $id) {
            $stored = $this->storedPassword($id);
            $this->assertTrue($newOnly->isCurrent($stored));
            $this->assertSame(self::PASSWORD, $newOnly->decrypt($stored, $this->organizationOf($id), $id));
            $this->assertSame(self::UPDATED_AT, $this->updatedAt($id));
        }

        $this->assertSame($currentBytes, $this->storedPassword($current));
    }

    public function testASecondRunIsIdempotent(): void
    {
        $organizationId = $this->fixtures->organization();
        $id = $this->connection($organizationId, self::OLD_KEY);
        $this->command()->run([]);
        $stored = $this->storedPassword($id);
        ftruncate($this->stdout, 0);
        rewind($this->stdout);

        $this->assertSame(0, $this->command()->run([]));

        $this->assertOutput("re-encrypted: 0\nalready current: 1\nunreadable: 0\n");
        $this->assertSame($stored, $this->storedPassword($id));
    }

    public function testCheckDecryptsEveryRowAndChangesNothing(): void
    {
        $organizationId = $this->fixtures->organization();
        $old = $this->connection($organizationId, self::OLD_KEY);
        $current = $this->connection($organizationId, self::NEW_KEY);
        $before = [$old => $this->storedPassword($old), $current => $this->storedPassword($current)];

        $this->assertSame(0, $this->command()->run(['--check']));

        $this->assertOutput("due: 1\nalready current: 1\nunreadable: 0\n");

        foreach ($before as $id => $stored) {
            $this->assertSame($stored, $this->storedPassword($id));
            $this->assertSame(self::UPDATED_AT, $this->updatedAt($id));
        }
    }

    public function testUnreadableRowsAreSkippedReportedAndFail(): void
    {
        $organizationId = $this->fixtures->organization();
        $lost = $this->connection($organizationId, self::LOST_KEY);
        $old = $this->connection($organizationId, self::OLD_KEY);
        $corrupt = $this->connection($organizationId, self::NEW_KEY);
        $corruptBytes = $this->storedPassword($corrupt);
        $corruptBytes[40] = chr(ord($corruptBytes[40]) ^ 0x01);
        $this->setStoredPassword($corrupt, $corruptBytes);
        $lostBytes = $this->storedPassword($lost);

        $this->assertSame(1, $this->command()->run(['--check']));
        $this->assertOutput("due: 1\nalready current: 0\nunreadable: 2\nunreadable ids: {$lost}, {$corrupt}\n");

        ftruncate($this->stdout, 0);
        rewind($this->stdout);

        $this->assertSame(1, $this->command()->run([]));
        $this->assertOutput("re-encrypted: 1\nalready current: 1\nunreadable: 1\nunreadable ids: {$lost}\n");
        $this->assertSame($lostBytes, $this->storedPassword($lost));
        $newOnly = new PasswordCipher(new EncryptionKeys(self::NEW_KEY));
        $this->assertTrue($newOnly->isCurrent($this->storedPassword($old)));
    }

    public function testNoRowsGiveZeroCounts(): void
    {
        $this->assertSame(0, $this->command()->run([]));
        $this->assertOutput("re-encrypted: 0\nalready current: 0\nunreadable: 0\n");
    }

    public function testRowsWithoutAPasswordAreIgnored(): void
    {
        $organizationId = $this->fixtures->organization();
        $this->fixtures->smtpConnection(organizationId: $organizationId);
        $this->fixtures->smtpConnection(organizationId: $organizationId);

        $this->assertSame(0, $this->command()->run(['--check']));
        $this->assertOutput("due: 0\nalready current: 0\nunreadable: 0\n");
    }

    public function testARowRewrittenBetweenTheListingAndTheLockIsNotOverwritten(): void
    {
        $organizationId = $this->fixtures->organization();
        $id = $this->connection($organizationId, self::OLD_KEY);
        $newer = (new PasswordCipher(new EncryptionKeys(self::NEW_KEY)))
            ->encrypt('newer-password-from-a-patch', $organizationId, $id);
        $config = DatabaseConfig::fromEnv();
        $factory = new PdoFactory();
        $hooked = new LockHookPdo($factory->dsn($config, true), $config->user, $config->password, $factory->options());
        $hooked->beforeNextLock(function () use ($id, $newer): void {
            // A second connection commits a write with the current key before the lock is taken.
            $this->setStoredPassword($id, $newer);
        });
        $connections = $this->createStub(ConnectionFactory::class);
        $connections->method('create')->willReturn($hooked);
        $command = new ReencryptSmtpPasswordsCommand(
            static fn (): Container => new Container($connections),
            $this->stdout,
            $this->stderr,
        );

        $this->assertSame(0, $command->run([]));

        $this->assertOutput("re-encrypted: 0\nalready current: 1\nunreadable: 0\n");
        $this->assertSame($newer, $this->storedPassword($id));
    }

    public function testInvalidUsageIsRefused(): void
    {
        $this->assertSame(2, $this->command()->run(['--force']));
        $this->assertSame("Usage: reencrypt-smtp-passwords [--check]\n", $this->read($this->stderr));
        $this->assertSame('', $this->read($this->stdout));
    }

    public function testAMissingKeyIsReportedWithoutAValue(): void
    {
        putenv('SMTP_ENCRYPTION_KEY');

        $this->assertSame(1, $this->command()->run([]));
        $this->assertStringContainsString('SMTP_ENCRYPTION_KEY', $this->read($this->stderr));
        $this->assertStringNotContainsString(self::OLD_KEY, $this->read($this->stderr));
        $this->assertSame('', $this->read($this->stdout));
    }

    public function testTheEntryPointRunsTheCommand(): void
    {
        $organizationId = $this->fixtures->organization();
        $id = $this->connection($organizationId, self::OLD_KEY);

        $process = proc_open(
            [PHP_BINARY, self::SOURCE_ROOT . '/bin/reencrypt-smtp-passwords', '--check'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            self::SOURCE_ROOT,
            getenv(),
        );
        $this->assertIsResource($process);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $this->assertSame(0, proc_close($process), $stderr);
        $this->assertSame("due: 1\nalready current: 0\nunreadable: 0\n", $stdout);
        $this->assertNoSecretIn($stdout . $stderr, $this->storedPassword($id));
    }

    private function command(): ReencryptSmtpPasswordsCommand
    {
        return ReencryptSmtpPasswordsCommand::fromEnvironment($this->stdout, $this->stderr);
    }

    /**
     * Inserts a connection whose password is encrypted with the given key, with a fixed
     * `updated_at`.
     */
    private function connection(int $organizationId, string $key): int
    {
        $id = $this->fixtures->smtpConnection(organizationId: $organizationId, username: 'mailer');
        $stored = (new PasswordCipher(new EncryptionKeys($key)))->encrypt(self::PASSWORD, $organizationId, $id);
        $this->setStoredPassword($id, $stored);

        return $id;
    }

    private function setStoredPassword(int $id, string $stored): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE smtp_connections SET password = :password, updated_at = :updated_at WHERE id = :id',
        );
        $statement->bindValue('password', $stored, PDO::PARAM_LOB);
        $statement->bindValue('updated_at', self::UPDATED_AT);
        $statement->bindValue('id', $id, PDO::PARAM_INT);
        $statement->execute();
    }

    private function storedPassword(int $id): string
    {
        return (string) $this->column('password', $id);
    }

    private function updatedAt(int $id): string
    {
        return (string) $this->column('updated_at', $id);
    }

    private function organizationOf(int $id): int
    {
        return (int) $this->column('organization_id', $id);
    }

    private function column(string $column, int $id): mixed
    {
        $statement = $this->pdo->prepare("SELECT {$column} FROM smtp_connections WHERE id = :id");
        $statement->execute(['id' => $id]);

        return $statement->fetchColumn();
    }

    private function assertOutput(string $expected): void
    {
        $stdout = $this->read($this->stdout);

        $this->assertSame($expected, $stdout);
        $this->assertSame('', $this->read($this->stderr));
        $this->assertNoSecretIn($stdout, '');
    }

    private function assertNoSecretIn(string $output, string $stored): void
    {
        foreach ([self::PASSWORD, self::OLD_KEY, self::NEW_KEY, self::LOST_KEY] as $secret) {
            $this->assertStringNotContainsString($secret, $output);
        }

        if ($stored !== '') {
            $this->assertStringNotContainsString($stored, $output);
            $this->assertStringNotContainsString(bin2hex($stored), $output);
        }
    }

    /**
     * @param resource $stream
     */
    private function read($stream): string
    {
        rewind($stream);

        return (string) stream_get_contents($stream);
    }
}
