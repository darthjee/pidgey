<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\PdoFactory;
use Pidgey\Model\Organization;
use Pidgey\Model\Row;
use Pidgey\Repository\OrganizationRepository;
use Pidgey\Tests\Support\RepositoryTestCase;

#[CoversClass(OrganizationRepository::class)]
#[UsesClass(Organization::class)]
#[UsesClass(Row::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(PdoFactory::class)]
final class OrganizationRepositoryTest extends RepositoryTestCase
{
    private OrganizationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new OrganizationRepository($this->pdo);
    }

    public function testCreateReturnsTheStoredOrganization(): void
    {
        $organization = $this->repository->create('Acme');

        $this->assertGreaterThan(0, $organization->id);
        $this->assertSame('Acme', $organization->name);
        $this->assertSame('UTC', $organization->createdAt->getTimezone()->getName());
        $this->assertEqualsWithDelta(time(), $organization->createdAt->getTimestamp(), 60);
        $this->assertEquals($organization->createdAt, $organization->updatedAt);
        $this->assertEquals($organization, $this->repository->findById($organization->id));
    }

    public function testCreateStoresTheNameAsGiven(): void
    {
        $this->assertSame(' Acme ', $this->repository->create(' Acme ')->name);
    }

    public function testFindByIdFindsAFixtureRow(): void
    {
        $id = $this->fixtures->organization('Fixture Org');

        $organization = $this->repository->findById($id);

        $this->assertNotNull($organization);
        $this->assertSame($id, $organization->id);
        $this->assertSame('Fixture Org', $organization->name);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $id = $this->fixtures->organization();

        $this->assertNull($this->repository->findById($id + 1000000));
    }
}
