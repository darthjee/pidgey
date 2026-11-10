<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Repository;

use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Crypto\EncryptionKeys;
use Pidgey\Crypto\PasswordCipher;
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
#[UsesClass(EncryptionKeys::class)]
#[UsesClass(PasswordCipher::class)]
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

    public function testTheLargestEncryptedPasswordStoresAndReadsBackUnchanged(): void
    {
        $organizationId = $this->fixtures->organization();
        $cipher = new PasswordCipher(new EncryptionKeys('repository-test-smtp-encryption-key-0123456789abcdef'));
        $password = str_repeat("\u{1F426}", 255);
        $connection = $this->repository->create(
            $organizationId,
            'Largest password',
            'smtp.example.com',
            587,
            SmtpEncryption::Tls,
            'mailer',
            null,
            'noreply@example.com',
        );
        $stored = $cipher->encrypt($password, $organizationId, $connection->id);
        $this->assertSame(1069, strlen($stored));

        $statement = $this->pdo->prepare('UPDATE smtp_connections SET password = :password WHERE id = :id');
        $statement->execute(['password' => $stored, 'id' => $connection->id]);

        $read = $this->repository->findInOrganization($organizationId, $connection->id)?->encryptedPassword;
        $this->assertSame($stored, $read);
        $this->assertSame($password, $cipher->decrypt((string) $read, $organizationId, $connection->id));
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

    public function testLockInOrganizationReadsTheRow(): void
    {
        $organizationId = $this->fixtures->organization();
        $id = $this->fixtures->smtpConnection(organizationId: $organizationId, host: 'smtp.example.net');

        $connection = $this->repository->lockInOrganization($organizationId, $id);

        $this->assertNotNull($connection);
        $this->assertSame($id, $connection->id);
        $this->assertSame('smtp.example.net', $connection->host);
        $this->assertEquals($this->repository->findInOrganization($organizationId, $id), $connection);
    }

    public function testLockInOrganizationIgnoresUnknownAndForeignRows(): void
    {
        $organizationId = $this->fixtures->organization();
        $id = $this->fixtures->smtpConnection(organizationId: $organizationId);
        $foreignId = $this->fixtures->smtpConnection();

        $this->assertNull($this->repository->lockInOrganization($organizationId, $id + 1000000));
        $this->assertNull($this->repository->lockInOrganization($organizationId, $foreignId));
    }

    public function testUpdateWritesTheSettingsAndKeepsThePasswordBytes(): void
    {
        $organizationId = $this->fixtures->organization();
        $password = "\x00\xff" . random_bytes(16);
        $id = $this->fixtures->smtpConnection(
            organizationId: $organizationId,
            encryption: 'tls',
            username: 'mailer',
            password: $password,
        );
        $stored = $this->repository->findInOrganization($organizationId, $id);
        $this->assertNotNull($stored);

        $this->repository->update(
            $this->merged($stored, name: 'Renamed', host: 'smtp.example.org', port: 465, username: 'other'),
            false,
            null,
        );

        $updated = $this->repository->findInOrganization($organizationId, $id);
        $this->assertNotNull($updated);
        $this->assertSame('Renamed', $updated->name);
        $this->assertSame('smtp.example.org', $updated->host);
        $this->assertSame(465, $updated->port);
        $this->assertSame(SmtpEncryption::Ssl, $updated->encryption);
        $this->assertSame('other', $updated->username);
        $this->assertSame('changed@example.com', $updated->fromAddress);
        $this->assertSame($password, $updated->encryptedPassword);
    }

    public function testUpdateWritesANewPassword(): void
    {
        $organizationId = $this->fixtures->organization();
        $id = $this->fixtures->smtpConnection(
            organizationId: $organizationId,
            encryption: 'tls',
            username: 'mailer',
            password: 'old-bytes',
        );
        $stored = $this->repository->findInOrganization($organizationId, $id);
        $this->assertNotNull($stored);
        $replacement = "\x00\x01" . random_bytes(24);

        $this->repository->update($this->merged($stored), true, $replacement);

        $this->assertSame(
            $replacement,
            $this->repository->findInOrganization($organizationId, $id)?->encryptedPassword,
        );
    }

    public function testUpdateClearsTheCredentials(): void
    {
        $organizationId = $this->fixtures->organization();
        $id = $this->fixtures->smtpConnection(
            organizationId: $organizationId,
            encryption: 'tls',
            username: 'mailer',
            password: 'old-bytes',
        );
        $stored = $this->repository->findInOrganization($organizationId, $id);
        $this->assertNotNull($stored);

        $this->repository->update($this->merged($stored, username: null), true, null);

        $updated = $this->repository->findInOrganization($organizationId, $id);
        $this->assertNotNull($updated);
        $this->assertNull($updated->username);
        $this->assertNull($updated->encryptedPassword);
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function activeStates(): array
    {
        return [
            'stored active' => [true],
            'stored inactive' => [false],
        ];
    }

    #[DataProvider('activeStates')]
    public function testUpdateNeverWritesActive(bool $storedActive): void
    {
        $organizationId = $this->fixtures->organization();
        $id = $this->fixtures->smtpConnection(organizationId: $organizationId, active: !$storedActive);
        $stale = $this->repository->findInOrganization($organizationId, $id);
        $this->assertNotNull($stale);
        $this->pdo->prepare('UPDATE smtp_connections SET active = :active WHERE id = :id')
            ->execute(['active' => (int) $storedActive, 'id' => $id]);

        $this->repository->update($this->merged($stale, name: 'Renamed', active: $stale->active), false, null);

        $updated = $this->repository->findInOrganization($organizationId, $id);
        $this->assertNotNull($updated);
        $this->assertSame('Renamed', $updated->name);
        $this->assertSame($storedActive, $updated->active);
    }

    public function testUpdateIgnoresAConnectionOfAnotherOrganization(): void
    {
        $organizationId = $this->fixtures->organization();
        $foreignId = $this->fixtures->smtpConnection(name: 'Foreign');
        $foreign = $this->repository->listByOrganization($this->organizationOf($foreignId))[0];
        $forged = new SmtpConnection(
            $foreign->id,
            $organizationId,
            'Hijacked',
            $foreign->host,
            $foreign->port,
            $foreign->encryption,
            null,
            null,
            $foreign->fromAddress,
            false,
            $foreign->createdAt,
            $foreign->updatedAt,
        );

        $this->repository->update($forged, true, null);

        $this->assertSame(
            'Foreign',
            $this->repository->findInOrganization($foreign->organizationId, $foreignId)?->name,
        );
    }

    public function testDeleteRemovesTheRowActiveOrNot(): void
    {
        $organizationId = $this->fixtures->organization();
        $activeId = $this->fixtures->smtpConnection(organizationId: $organizationId, active: true);
        $inactiveId = $this->fixtures->smtpConnection(organizationId: $organizationId);

        $this->assertTrue($this->repository->delete($organizationId, $activeId));
        $this->assertTrue($this->repository->delete($organizationId, $inactiveId));

        $this->assertSame([], $this->repository->listByOrganization($organizationId));
    }

    public function testDeleteIgnoresUnknownAndForeignRows(): void
    {
        $organizationId = $this->fixtures->organization();
        $id = $this->fixtures->smtpConnection(organizationId: $organizationId);
        $foreignId = $this->fixtures->smtpConnection();

        $this->assertFalse($this->repository->delete($organizationId, $id + 1000000));
        $this->assertFalse($this->repository->delete($organizationId, $foreignId));
        $this->assertNotNull($this->repository->findInOrganization($this->organizationOf($foreignId), $foreignId));
    }

    public function testActivateMakesTheTargetTheOnlyActiveConnection(): void
    {
        $organizationId = $this->fixtures->organization();
        $previousId = $this->fixtures->smtpConnection(organizationId: $organizationId, active: true);
        $targetId = $this->fixtures->smtpConnection(organizationId: $organizationId);
        $otherId = $this->fixtures->smtpConnection(organizationId: $organizationId);
        $foreignActiveId = $this->fixtures->smtpConnection(active: true);

        $this->repository->activate($organizationId, $targetId);

        $this->assertSame(
            [$previousId => false, $targetId => true, $otherId => false],
            $this->activeStatesOf($organizationId),
        );
        $this->assertTrue(
            $this->repository->findInOrganization($this->organizationOf($foreignActiveId), $foreignActiveId)?->active,
        );
    }

    public function testActivateIsIdempotent(): void
    {
        $organizationId = $this->fixtures->organization();
        $targetId = $this->fixtures->smtpConnection(organizationId: $organizationId);
        $otherId = $this->fixtures->smtpConnection(organizationId: $organizationId);

        $this->repository->activate($organizationId, $targetId);
        $this->repository->activate($organizationId, $targetId);

        $this->assertSame([$targetId => true, $otherId => false], $this->activeStatesOf($organizationId));
    }

    public function testActivateDoesNotTouchAForeignConnection(): void
    {
        $organizationId = $this->fixtures->organization();
        $ownActiveId = $this->fixtures->smtpConnection(organizationId: $organizationId, active: true);
        $foreignId = $this->fixtures->smtpConnection();

        $this->repository->activate($organizationId, $foreignId);

        $this->assertFalse(
            $this->repository->findInOrganization($this->organizationOf($foreignId), $foreignId)?->active,
        );
        $this->assertSame([$ownActiveId => false], $this->activeStatesOf($organizationId));
    }

    public function testDeactivateChangesTheTargetOnly(): void
    {
        $organizationId = $this->fixtures->organization();
        $targetId = $this->fixtures->smtpConnection(organizationId: $organizationId, active: true);
        $otherId = $this->fixtures->smtpConnection(organizationId: $organizationId);

        $this->repository->deactivate($organizationId, $targetId);
        $this->repository->deactivate($organizationId, $targetId);

        $this->assertSame([$targetId => false, $otherId => false], $this->activeStatesOf($organizationId));
    }

    public function testDeactivateLeavesTheActiveConnectionWhenAnotherIsTargeted(): void
    {
        $organizationId = $this->fixtures->organization();
        $activeId = $this->fixtures->smtpConnection(organizationId: $organizationId, active: true);
        $targetId = $this->fixtures->smtpConnection(organizationId: $organizationId);

        $this->repository->deactivate($organizationId, $targetId);

        $this->assertSame([$activeId => true, $targetId => false], $this->activeStatesOf($organizationId));
    }

    public function testDeactivateDoesNotTouchAForeignConnection(): void
    {
        $organizationId = $this->fixtures->organization();
        $foreignId = $this->fixtures->smtpConnection(active: true);

        $this->repository->deactivate($organizationId, $foreignId);

        $this->assertTrue(
            $this->repository->findInOrganization($this->organizationOf($foreignId), $foreignId)?->active,
        );
    }

    public function testTheUniqueIndexRejectsASecondActiveConnectionOfAnOrganization(): void
    {
        $organizationId = $this->fixtures->organization();
        $this->fixtures->smtpConnection(organizationId: $organizationId, active: true);

        try {
            $this->fixtures->smtpConnection(organizationId: $organizationId, active: true);
            $this->fail('Expected the unique index to reject a second active connection');
        } catch (PDOException $error) {
            $this->assertSame('23000', $error->getCode());
            $this->assertStringContainsString('uq_smtp_connections_active_organization_id', $error->getMessage());
        }
    }

    public function testTheUniqueIndexRejectsActivatingASecondRowDirectly(): void
    {
        $organizationId = $this->fixtures->organization();
        $this->fixtures->smtpConnection(organizationId: $organizationId, active: true);
        $secondId = $this->fixtures->smtpConnection(organizationId: $organizationId);

        $this->expectException(PDOException::class);

        $this->pdo->prepare('UPDATE smtp_connections SET active = TRUE WHERE id = :id')
            ->execute(['id' => $secondId]);
    }

    public function testTheUniqueIndexAllowsInactiveRowsAndOneActivePerOrganization(): void
    {
        $firstOrganizationId = $this->fixtures->organization();
        $secondOrganizationId = $this->fixtures->organization();

        foreach ([$firstOrganizationId, $secondOrganizationId] as $organizationId) {
            $this->fixtures->smtpConnection(organizationId: $organizationId);
            $this->fixtures->smtpConnection(organizationId: $organizationId);
            $this->fixtures->smtpConnection(organizationId: $organizationId, active: true);
        }

        $this->assertCount(3, $this->repository->listByOrganization($firstOrganizationId));
        $this->assertCount(3, $this->repository->listByOrganization($secondOrganizationId));
    }

    /**
     * A copy of the stored row with some settings replaced, as a handler merges a request.
     */
    private function merged(
        SmtpConnection $stored,
        string $name = 'Updated',
        string $host = 'smtp.example.com',
        int $port = 587,
        ?string $username = 'mailer',
        ?bool $active = null,
    ): SmtpConnection {
        return new SmtpConnection(
            $stored->id,
            $stored->organizationId,
            $name,
            $host,
            $port,
            $port === 465 ? SmtpEncryption::Ssl : SmtpEncryption::Tls,
            $username,
            'ignored-bytes',
            'changed@example.com',
            $active ?? !$stored->active,
            $stored->createdAt,
            $stored->updatedAt,
        );
    }

    /**
     * The organization id of a connection.
     */
    private function organizationOf(int $connectionId): int
    {
        $statement = $this->pdo->prepare('SELECT organization_id FROM smtp_connections WHERE id = :id');
        $statement->execute(['id' => $connectionId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * The active flag of every connection of an organization, by id.
     *
     * @return array<int, bool>
     */
    private function activeStatesOf(int $organizationId): array
    {
        $states = [];

        foreach ($this->repository->listByOrganization($organizationId) as $connection) {
            $states[$connection->id] = $connection->active;
        }

        return $states;
    }
}
