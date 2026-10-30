<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Repository;

use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\PdoFactory;
use Pidgey\Model\ApiToken;
use Pidgey\Model\Row;
use Pidgey\Repository\ApiTokenRepository;
use Pidgey\Tests\Support\RepositoryTestCase;

#[CoversClass(ApiTokenRepository::class)]
#[UsesClass(ApiToken::class)]
#[UsesClass(Row::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(PdoFactory::class)]
final class ApiTokenRepositoryTest extends RepositoryTestCase
{
    private ApiTokenRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new ApiTokenRepository($this->pdo);
    }

    public function testCreateReturnsEveryField(): void
    {
        $organizationId = $this->fixtures->organization();
        $tokenHash = str_repeat('a', 64);

        $token = $this->repository->create($organizationId, 'CI pipeline', 'pdg_abcd', $tokenHash, ['send']);

        $this->assertGreaterThan(0, $token->id);
        $this->assertSame($organizationId, $token->organizationId);
        $this->assertSame('CI pipeline', $token->name);
        $this->assertSame('pdg_abcd', $token->tokenPrefix);
        $this->assertSame($tokenHash, $token->tokenHash);
        $this->assertSame(['send'], $token->scopes);
        $this->assertSame('UTC', $token->createdAt->getTimezone()->getName());
        $this->assertNull($token->lastUsedAt);
        $this->assertNull($token->revokedAt);
        $this->assertEquals($token, $this->repository->findInOrganization($organizationId, $token->id));
    }

    public function testCreateRoundTripsSeveralScopesInOrder(): void
    {
        $organizationId = $this->fixtures->organization();

        $token = $this->repository->create(
            $organizationId,
            'Multi',
            'pdg_',
            str_repeat('b', 64),
            ['send', 'read', 'admin'],
        );

        $this->assertSame(['send', 'read', 'admin'], $token->scopes);
        $this->assertSame(
            ['send', 'read', 'admin'],
            $this->repository->findByTokenHash(str_repeat('b', 64))?->scopes,
        );
    }

    public function testCreateRejectsADuplicateTokenHash(): void
    {
        $tokenHash = str_repeat('c', 64);
        $this->fixtures->apiToken(tokenHash: $tokenHash);

        $this->expectException(PDOException::class);

        $this->repository->create($this->fixtures->organization(), 'Copy', 'pdg_', $tokenHash, ['send']);
    }

    public function testCreateRejectsAnUnknownOrganization(): void
    {
        $this->fixtures->organization();
        $maxId = (int) $this->pdo->query('SELECT MAX(id) FROM organizations')->fetchColumn();

        $this->expectException(PDOException::class);

        $this->repository->create($maxId + 1000000, 'Orphan', 'pdg_', str_repeat('d', 64), ['send']);
    }

    public function testFindInOrganizationFindsAFixtureRow(): void
    {
        $organizationId = $this->fixtures->organization();
        $id = $this->fixtures->apiToken(organizationId: $organizationId, name: 'Deploy');

        $token = $this->repository->findInOrganization($organizationId, $id);

        $this->assertSame($id, $token?->id);
        $this->assertSame($organizationId, $token?->organizationId);
        $this->assertSame('Deploy', $token?->name);
        $this->assertSame('pdg_', $token?->tokenPrefix);
        $this->assertSame(['send'], $token?->scopes);
    }

    public function testFindInOrganizationReturnsNullWhenNotFound(): void
    {
        $organizationId = $this->fixtures->organization();
        $id = $this->fixtures->apiToken(organizationId: $organizationId);

        $this->assertNull($this->repository->findInOrganization($organizationId, $id + 1000000));
    }

    public function testFindInOrganizationIgnoresATokenOfAnotherOrganization(): void
    {
        $organizationId = $this->fixtures->organization();
        $otherTokenId = $this->fixtures->apiToken();

        $this->assertNull($this->repository->findInOrganization($organizationId, $otherTokenId));
    }

    public function testFindByTokenHashFindsATokenOfAnyOrganization(): void
    {
        $tokenHash = str_repeat('e', 64);
        $firstOrganizationId = $this->fixtures->organization();
        $secondOrganizationId = $this->fixtures->organization();
        $this->fixtures->apiToken(organizationId: $firstOrganizationId);
        $id = $this->fixtures->apiToken(organizationId: $secondOrganizationId, tokenHash: $tokenHash);

        $token = $this->repository->findByTokenHash($tokenHash);

        $this->assertSame($id, $token?->id);
        $this->assertSame($secondOrganizationId, $token?->organizationId);
    }

    public function testFindByTokenHashReturnsNullWhenNotFound(): void
    {
        $this->fixtures->apiToken(tokenHash: str_repeat('e', 64));

        $this->assertNull($this->repository->findByTokenHash(str_repeat('f', 64)));
    }

    public function testFindByTokenHashReturnsARevokedToken(): void
    {
        $tokenHash = str_repeat('1', 64);
        $id = $this->fixtures->apiToken(
            tokenHash: $tokenHash,
            lastUsedAt: '2026-03-01 09:00:00',
            revokedAt: '2026-03-02 10:00:00',
        );

        $token = $this->repository->findByTokenHash($tokenHash);

        $this->assertSame($id, $token?->id);
        $this->assertSame('2026-03-01 09:00:00', $token?->lastUsedAt?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-02 10:00:00', $token?->revokedAt?->format('Y-m-d H:i:s'));
    }
}
