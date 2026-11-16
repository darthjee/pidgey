<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\PdoFactory;
use Pidgey\Model\Row;
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;
use Pidgey\Repository\Sql;
use Pidgey\Repository\UserRepository;
use Pidgey\Tests\Support\UserRepositoryTestCase;

#[CoversClass(UserRepository::class)]
#[UsesClass(User::class)]
#[UsesClass(UserStatus::class)]
#[UsesClass(Row::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(PdoFactory::class)]
#[UsesClass(Sql::class)]
final class UserRepositoryFindTest extends UserRepositoryTestCase
{
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

    public function testFindApprovedEmailReturnsTheStoredValueForADifferentCase(): void
    {
        $organizationId = $this->fixtures->organization();
        $this->fixtures->user(organizationId: $organizationId, email: 'Alice@Example.com');

        $this->assertSame(
            'Alice@Example.com',
            $this->repository->findApprovedEmailInOrganization($organizationId, 'alice@example.COM'),
        );
    }

    public function testFindApprovedEmailIgnoresPendingDeniedAndOtherOrganizations(): void
    {
        $organizationId = $this->fixtures->organization();
        $this->fixtures->user(organizationId: $organizationId, email: 'pending@example.com', status: 'pending');
        $this->fixtures->user(organizationId: $organizationId, email: 'denied@example.com', status: 'denied');
        $this->fixtures->user(email: 'other@example.com');

        foreach (['pending@example.com', 'denied@example.com', 'other@example.com', 'nobody@example.com'] as $email) {
            $this->assertNull($this->repository->findApprovedEmailInOrganization($organizationId, $email));
        }
    }
}
