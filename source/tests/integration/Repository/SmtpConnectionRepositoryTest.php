<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Repository;

use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\PdoFactory;
use Pidgey\Model\Row;
use Pidgey\Model\SmtpConnection;
use Pidgey\Model\SmtpEncryption;
use Pidgey\Repository\SmtpConnectionRepository;
use Pidgey\Tests\Support\RepositoryTestCase;

#[CoversClass(SmtpConnectionRepository::class)]
#[UsesClass(SmtpConnection::class)]
#[UsesClass(Row::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(PdoFactory::class)]
final class SmtpConnectionRepositoryTest extends RepositoryTestCase
{
    private SmtpConnectionRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new SmtpConnectionRepository($this->pdo);
    }

    public function testCreateWithoutCredentialsReturnsEveryField(): void
    {
        $organizationId = $this->fixtures->organization();

        $connection = $this->repository->create(
            $organizationId,
            'Primary relay',
            'smtp.example.com',
            587,
            SmtpEncryption::Tls,
            null,
            null,
            'noreply@example.com',
        );

        $this->assertGreaterThan(0, $connection->id);
        $this->assertSame($organizationId, $connection->organizationId);
        $this->assertSame('Primary relay', $connection->name);
        $this->assertSame('smtp.example.com', $connection->host);
        $this->assertSame(587, $connection->port);
        $this->assertSame(SmtpEncryption::Tls, $connection->encryption);
        $this->assertNull($connection->username);
        $this->assertNull($connection->encryptedPassword);
        $this->assertFalse($connection->hasPassword());
        $this->assertSame('noreply@example.com', $connection->fromAddress);
        $this->assertFalse($connection->active);
        $this->assertSame('UTC', $connection->createdAt->getTimezone()->getName());
        $this->assertSame('UTC', $connection->updatedAt->getTimezone()->getName());
        $this->assertEquals($connection, $this->repository->findInOrganization($organizationId, $connection->id));
    }

    public function testCreateWithCredentialsRoundTripsThePasswordBytes(): void
    {
        $organizationId = $this->fixtures->organization();
        $password = "\x00\xff\xfe" . random_bytes(32) . "\x00";

        $connection = $this->repository->create(
            $organizationId,
            'Authenticated relay',
            'smtp.example.com',
            465,
            SmtpEncryption::Ssl,
            'mailer',
            $password,
            'noreply@example.com',
        );

        $this->assertSame('mailer', $connection->username);
        $this->assertSame($password, $connection->encryptedPassword);
        $this->assertTrue($connection->hasPassword());
        $this->assertSame(
            $password,
            $this->repository->findInOrganization($organizationId, $connection->id)?->encryptedPassword,
        );

        $statement = $this->pdo->prepare('SELECT password FROM smtp_connections WHERE id = :id');
        $statement->execute(['id' => $connection->id]);
        $this->assertSame($password, $statement->fetchColumn());
    }

    public function testCreateStartsInactiveEvenWhenAnotherConnectionIsActive(): void
    {
        $organizationId = $this->fixtures->organization();
        $this->fixtures->smtpConnection(organizationId: $organizationId, active: true);

        $connection = $this->repository->create(
            $organizationId,
            'Second relay',
            'smtp.example.com',
            25,
            SmtpEncryption::None,
            null,
            null,
            'noreply@example.com',
        );

        $this->assertFalse($connection->active);
    }

    public function testCreateRejectsAnUnknownOrganization(): void
    {
        $this->fixtures->organization();
        $maxId = (int) $this->pdo->query('SELECT MAX(id) FROM organizations')->fetchColumn();

        $this->expectException(PDOException::class);

        $this->repository->create(
            $maxId + 1000000,
            'Orphan',
            'smtp.example.com',
            25,
            SmtpEncryption::None,
            null,
            null,
            'noreply@example.com',
        );
    }

    #[DataProvider('encryptions')]
    public function testFindInOrganizationFindsAFixtureRow(SmtpEncryption $encryption): void
    {
        $organizationId = $this->fixtures->organization();
        $password = "\x00\xff" . random_bytes(16);
        $id = $this->fixtures->smtpConnection(
            organizationId: $organizationId,
            name: 'Relay',
            host: 'smtp.example.org',
            port: 2525,
            encryption: $encryption->value,
            username: 'mailer',
            password: $password,
            fromAddress: 'sender@example.org',
            active: true,
        );

        $connection = $this->repository->findInOrganization($organizationId, $id);

        $this->assertNotNull($connection);
        $this->assertSame($id, $connection->id);
        $this->assertSame($organizationId, $connection->organizationId);
        $this->assertSame('Relay', $connection->name);
        $this->assertSame('smtp.example.org', $connection->host);
        $this->assertSame(2525, $connection->port);
        $this->assertSame($encryption, $connection->encryption);
        $this->assertSame('mailer', $connection->username);
        $this->assertSame($password, $connection->encryptedPassword);
        $this->assertSame('sender@example.org', $connection->fromAddress);
        $this->assertTrue($connection->active);
    }

    /**
     * @return array<string, array{SmtpEncryption}>
     */
    public static function encryptions(): array
    {
        return [
            'none' => [SmtpEncryption::None],
            'tls' => [SmtpEncryption::Tls],
            'ssl' => [SmtpEncryption::Ssl],
        ];
    }

    public function testFindInOrganizationReturnsNullWhenNotFound(): void
    {
        $organizationId = $this->fixtures->organization();
        $id = $this->fixtures->smtpConnection(organizationId: $organizationId);

        $this->assertNull($this->repository->findInOrganization($organizationId, $id + 1000000));
    }

    public function testFindInOrganizationIgnoresAConnectionOfAnotherOrganization(): void
    {
        $organizationId = $this->fixtures->organization();
        $otherConnectionId = $this->fixtures->smtpConnection();

        $this->assertNull($this->repository->findInOrganization($organizationId, $otherConnectionId));
    }

    public function testListByOrganizationReturnsTheOrganizationRowsInIdOrder(): void
    {
        $organizationId = $this->fixtures->organization();
        $firstId = $this->fixtures->smtpConnection(organizationId: $organizationId);
        $this->fixtures->smtpConnection();
        $secondId = $this->fixtures->smtpConnection(organizationId: $organizationId);

        $connections = $this->repository->listByOrganization($organizationId);

        $this->assertSame(
            [$firstId, $secondId],
            array_map(static fn (SmtpConnection $connection): int => $connection->id, $connections),
        );
        $this->assertContainsOnlyInstancesOf(SmtpConnection::class, $connections);
    }

    public function testListByOrganizationReturnsAnEmptyListWhenThereAreNone(): void
    {
        $organizationId = $this->fixtures->organization();
        $this->fixtures->smtpConnection();

        $this->assertSame([], $this->repository->listByOrganization($organizationId));
    }
}
