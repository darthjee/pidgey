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
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;
use Pidgey\Repository\UserRepository;
use Pidgey\Tests\Support\Fixtures;
use Pidgey\Tests\Support\RepositoryTestCase;

#[CoversClass(UserRepository::class)]
#[UsesClass(User::class)]
#[UsesClass(UserStatus::class)]
#[UsesClass(Row::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(PdoFactory::class)]
final class UserRepositoryTest extends RepositoryTestCase
{
    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new UserRepository($this->pdo);
    }

    public function testCreateReturnsEveryField(): void
    {
        $organizationId = $this->fixtures->organization();

        $user = $this->repository->create(
            $organizationId,
            'alice',
            'alice@example.com',
            Fixtures::PASSWORD_DIGEST,
            UserStatus::Pending,
        );

        $this->assertGreaterThan(0, $user->id);
        $this->assertSame($organizationId, $user->organizationId);
        $this->assertSame('alice', $user->username);
        $this->assertSame('alice@example.com', $user->email);
        $this->assertSame(Fixtures::PASSWORD_DIGEST, $user->passwordDigest);
        $this->assertFalse($user->isAdmin);
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertSame('UTC', $user->createdAt->getTimezone()->getName());
        $this->assertEqualsWithDelta(time(), $user->createdAt->getTimestamp(), 60);
        $this->assertEquals($user->createdAt, $user->updatedAt);
        $this->assertEquals($user, $this->repository->findById($user->id));
    }

    public function testCreateStoresIsAdminWhenPassed(): void
    {
        $user = $this->createUser('root', 'root@example.com', isAdmin: true);

        $this->assertTrue($user->isAdmin);
        $this->assertTrue($this->repository->findById($user->id)?->isAdmin);
    }

    /**
     * @return array<string, array{UserStatus}>
     */
    public static function statuses(): array
    {
        return [
            'pending' => [UserStatus::Pending],
            'approved' => [UserStatus::Approved],
            'denied' => [UserStatus::Denied],
        ];
    }

    #[DataProvider('statuses')]
    public function testCreateStoresTheStatusAsPassed(UserStatus $status): void
    {
        $user = $this->createUser('bob', 'bob@example.com', $status);

        $this->assertSame($status, $this->repository->findById($user->id)?->status);
    }

    public function testCreateRejectsAnUnknownOrganization(): void
    {
        $organizationId = $this->fixtures->organization();

        $this->expectException(PDOException::class);

        $this->repository->create(
            $organizationId + 1000000,
            'orphan',
            'orphan@example.com',
            Fixtures::PASSWORD_DIGEST,
            UserStatus::Approved,
        );
    }

    public function testFindByIdFindsAFixtureRow(): void
    {
        $id = $this->fixtures->user(username: 'carol');

        $this->assertSame('carol', $this->repository->findById($id)?->username);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $id = $this->fixtures->user();

        $this->assertNull($this->repository->findById($id + 1000000));
    }

    public function testFindByUsernameFindsAFixtureRow(): void
    {
        $id = $this->fixtures->user(username: 'dave');

        $this->assertSame($id, $this->repository->findByUsername('dave')?->id);
    }

    public function testFindByUsernameReturnsNullWhenNotFound(): void
    {
        $this->fixtures->user(username: 'dave');

        $this->assertNull($this->repository->findByUsername('david'));
    }

    public function testFindByEmailFindsAFixtureRow(): void
    {
        $id = $this->fixtures->user(email: 'erin@example.com');

        $this->assertSame($id, $this->repository->findByEmail('erin@example.com')?->id);
    }

    public function testFindByEmailReturnsNullWhenNotFound(): void
    {
        $this->fixtures->user(email: 'erin@example.com');

        $this->assertNull($this->repository->findByEmail('erin@example.org'));
    }

    public function testFindInOrganizationFindsAUserOfThatOrganization(): void
    {
        $organizationId = $this->fixtures->organization();
        $id = $this->fixtures->user(organizationId: $organizationId);

        $user = $this->repository->findInOrganization($organizationId, $id);

        $this->assertSame($id, $user?->id);
        $this->assertSame($organizationId, $user?->organizationId);
    }

    public function testFindInOrganizationReturnsNullWhenNotFound(): void
    {
        $organizationId = $this->fixtures->organization();
        $id = $this->fixtures->user(organizationId: $organizationId);

        $this->assertNull($this->repository->findInOrganization($organizationId, $id + 1000000));
    }

    public function testFindInOrganizationIgnoresAUserOfAnotherOrganization(): void
    {
        $organizationId = $this->fixtures->organization();
        $otherUserId = $this->fixtures->user();

        $this->assertNull($this->repository->findInOrganization($organizationId, $otherUserId));
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function duplicates(): array
    {
        return [
            'same username' => ['admin', 'a@example.com', 'admin', 'b@example.com'],
            'username case variant' => ['Admin', 'a@example.com', 'admin', 'b@example.com'],
            'username accent variant' => ['José', 'a@example.com', 'jose', 'b@example.com'],
            'same email' => ['first', 'admin@example.com', 'second', 'admin@example.com'],
            'email case variant' => ['first', 'Admin@example.com', 'second', 'admin@example.com'],
            'email accent variant' => ['first', 'josé@example.com', 'second', 'jose@example.com'],
        ];
    }

    #[DataProvider('duplicates')]
    public function testCreateRejectsDuplicates(
        string $firstUsername,
        string $firstEmail,
        string $secondUsername,
        string $secondEmail,
    ): void {
        $this->createUser($firstUsername, $firstEmail);

        $this->expectException(PDOException::class);

        $this->createUser($secondUsername, $secondEmail);
    }

    public function testFindByUsernameIgnoresCaseAndAccents(): void
    {
        $adminId = $this->fixtures->user(username: 'admin');
        $joseId = $this->fixtures->user(username: 'josé');

        $this->assertSame($adminId, $this->repository->findByUsername('ADMIN')?->id);
        $this->assertSame($joseId, $this->repository->findByUsername('JOSE')?->id);
    }

    public function testFindByEmailIgnoresCaseAndAccents(): void
    {
        $id = $this->fixtures->user(email: 'josé@example.com');

        $this->assertSame($id, $this->repository->findByEmail('JOSE@example.com')?->id);
    }

    /**
     * The collation is NO PAD: a trailing space is significant. Repositories store and compare
     * values as given; trimming belongs to the registration and login endpoints.
     */
    public function testTrailingSpaceIsADistinctValue(): void
    {
        $adminId = $this->fixtures->user(username: 'admin', email: 'admin@example.com');

        $this->assertNull($this->repository->findByUsername('admin '));
        $this->assertNull($this->repository->findByEmail('admin@example.com '));

        $spaced = $this->createUser('admin ', 'admin@example.com ');

        $this->assertNotSame($adminId, $spaced->id);
        $this->assertSame('admin ', $spaced->username);
        $this->assertSame('admin@example.com ', $spaced->email);
        $this->assertSame($spaced->id, $this->repository->findByUsername('admin ')?->id);
        $this->assertSame($adminId, $this->repository->findByUsername('admin')?->id);
    }

    private function createUser(
        string $username,
        string $email,
        UserStatus $status = UserStatus::Approved,
        bool $isAdmin = false,
    ): User {
        return $this->repository->create(
            $this->fixtures->organization(),
            $username,
            $email,
            Fixtures::PASSWORD_DIGEST,
            $status,
            $isAdmin,
        );
    }
}
