<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Container;
use Pidgey\Crypto\EncryptionKeys;
use Pidgey\Crypto\PasswordCipher;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\PdoFactory;
use Pidgey\Infra\Transaction;
use Pidgey\Tests\Support\IntegrationTestCase;

#[CoversClass(Container::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(EncryptionKeys::class)]
#[UsesClass(Env::class)]
#[UsesClass(PasswordCipher::class)]
#[UsesClass(PdoFactory::class)]
#[UsesClass(Transaction::class)]
final class ContainerTest extends IntegrationTestCase
{
    public function testPdoConnectsToTheTestDatabase(): void
    {
        $container = new Container();
        $pdo = $container->pdo();

        $this->assertSame(self::config()->name, $pdo->query('SELECT DATABASE()')->fetchColumn());
        $this->assertSame('+00:00', $pdo->query('SELECT @@session.time_zone')->fetchColumn());
        $this->assertSame($pdo, $container->pdo());
    }

    public function testTransactionRunsOnTheContainerConnection(): void
    {
        $container = new Container();

        $inTransaction = $container->transaction()->run(
            static fn (): bool => $container->pdo()->inTransaction(),
        );

        $this->assertTrue($inTransaction);
        $this->assertFalse($container->pdo()->inTransaction());
    }

    public function testPasswordCipherUsesTheTestEnvironmentKey(): void
    {
        $cipher = (new Container())->passwordCipher();

        $stored = $cipher->encrypt('integration-password', 1, 2);

        $this->assertSame('integration-password', $cipher->decrypt($stored, 1, 2));
        $this->assertSame(
            EncryptionKeys::keyIdOf((string) getenv('SMTP_ENCRYPTION_KEY')),
            substr($stored, 1, EncryptionKeys::KEY_ID_BYTES),
        );
    }
}
