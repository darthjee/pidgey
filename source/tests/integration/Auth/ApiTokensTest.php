<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Auth;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Auth\ApiTokenRevokeOutcome;
use Pidgey\Auth\ApiTokens;
use Pidgey\Auth\ApiTokenValue;
use Pidgey\Auth\CreatedApiToken;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\PdoFactory;
use Pidgey\Model\ApiToken;
use Pidgey\Model\Row;
use Pidgey\Repository\ApiTokenRepository;
use Pidgey\Tests\Support\RepositoryTestCase;

#[CoversClass(ApiTokens::class)]
#[UsesClass(ApiTokenRevokeOutcome::class)]
#[UsesClass(ApiTokenValue::class)]
#[UsesClass(CreatedApiToken::class)]
#[UsesClass(ApiToken::class)]
#[UsesClass(ApiTokenRepository::class)]
#[UsesClass(Row::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(PdoFactory::class)]
final class ApiTokensTest extends RepositoryTestCase
{
    public function testCreateStoresPrefixAndDigestNeverThePlainToken(): void
    {
        $organizationId = $this->fixtures->organization();

        $created = $this->apiTokens()->create($organizationId, 'CI', ['send']);

        $this->assertMatchesRegularExpression('/\Apdg_[0-9a-f]{64}\z/', $created->token);
        $this->assertSame('pdg_', $created->apiToken->tokenPrefix);
        $this->assertSame(hash('sha256', $created->token), $created->apiToken->tokenHash);
        $row = $this->pdo->query('SELECT * FROM api_tokens WHERE id = ' . $created->apiToken->id)
            ?->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);

        foreach ($row as $value) {
            $this->assertStringNotContainsString($created->token, (string) $value);
        }
    }

    public function testListIsScopedToTheOrganizationAndOrderedById(): void
    {
        $organizationId = $this->fixtures->organization();
        $first = $this->fixtures->apiToken(organizationId: $organizationId);
        $second = $this->fixtures->apiToken(organizationId: $organizationId, revokedAt: '2026-03-02 10:00:00');
        $this->fixtures->apiToken();

        $ids = array_map(static fn (ApiToken $t): int => $t->id, $this->apiTokens()->list($organizationId));

        $this->assertSame([$first, $second], $ids);
    }

    public function testRevokeOutcomes(): void
    {
        $organizationId = $this->fixtures->organization();
        $id = $this->fixtures->apiToken(organizationId: $organizationId);
        $foreign = $this->fixtures->apiToken();
        $apiTokens = $this->apiTokens();

        $this->assertSame(ApiTokenRevokeOutcome::Revoked, $apiTokens->revoke($organizationId, $id));
        $this->assertSame(ApiTokenRevokeOutcome::AlreadyRevoked, $apiTokens->revoke($organizationId, $id));
        $this->assertSame(ApiTokenRevokeOutcome::NotFound, $apiTokens->revoke($organizationId, $foreign));
        $this->assertSame(ApiTokenRevokeOutcome::NotFound, $apiTokens->revoke($organizationId, $id + 1000000));
    }

    public function testCreateFailureDoesNotLeakThePlainTokenInTheTrace(): void
    {
        $apiTokens = $this->apiTokens();

        try {
            $apiTokens->create($this->fixtures->organization() + 1000000, 'Orphan', ['send']);
            $this->fail('Expected a PDOException.');
        } catch (PDOException $exception) {
            $this->assertDoesNotMatchRegularExpression('/pdg_[0-9a-f]{8}/', $exception->getTraceAsString());
        }
    }

    private function apiTokens(): ApiTokens
    {
        return new ApiTokens(new ApiTokenRepository($this->pdo));
    }
}
