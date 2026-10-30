<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Model;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Model\ApiToken;
use Pidgey\Model\Row;
use UnexpectedValueException;

#[CoversClass(ApiToken::class)]
#[UsesClass(Row::class)]
final class ApiTokenTest extends TestCase
{
    private const TOKEN_HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testFromRowMapsEveryColumn(): void
    {
        $token = ApiToken::fromRow($this->row());

        $this->assertSame(41, $token->id);
        $this->assertSame(7, $token->organizationId);
        $this->assertSame('CI pipeline', $token->name);
        $this->assertSame('pdg_abcd', $token->tokenPrefix);
        $this->assertSame(self::TOKEN_HASH, $token->tokenHash);
        $this->assertSame(['send'], $token->scopes);
        $this->assertSame('2026-01-02 03:04:05', $token->createdAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $token->createdAt->getTimezone()->getName());
        $this->assertNull($token->lastUsedAt);
        $this->assertNull($token->revokedAt);
    }

    public function testFromRowMapsAUsedAndRevokedToken(): void
    {
        $token = ApiToken::fromRow(
            ['last_used_at' => '2026-01-03 10:00:00', 'revoked_at' => '2026-01-04 11:30:00'] + $this->row(),
        );

        $this->assertNotNull($token->lastUsedAt);
        $this->assertSame('2026-01-03 10:00:00', $token->lastUsedAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $token->lastUsedAt->getTimezone()->getName());
        $this->assertNotNull($token->revokedAt);
        $this->assertSame('2026-01-04 11:30:00', $token->revokedAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $token->revokedAt->getTimezone()->getName());
    }

    public function testFromRowMapsSeveralScopes(): void
    {
        $token = ApiToken::fromRow(['scopes' => '["send", "read"]'] + $this->row());

        $this->assertSame(['send', 'read'], $token->scopes);
    }

    public function testFromRowRejectsAMissingColumn(): void
    {
        $row = $this->row();
        unset($row['revoked_at']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(ApiToken::class . ": missing column 'revoked_at'");

        ApiToken::fromRow($row);
    }

    #[DataProvider('invalidScopes')]
    public function testFromRowRejectsInvalidScopes(string $scopes): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(ApiToken::class . ": column 'scopes' must hold a JSON list of strings");

        ApiToken::fromRow(['scopes' => $scopes] + $this->row());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidScopes(): array
    {
        return [
            'invalid JSON' => ['["send"'],
            'an object' => ['{"scope": "send"}'],
            'a list holding a number' => ['["send", 1]'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(): array
    {
        return [
            'id' => 41,
            'organization_id' => 7,
            'name' => 'CI pipeline',
            'token_prefix' => 'pdg_abcd',
            'token_hash' => self::TOKEN_HASH,
            'scopes' => '["send"]',
            'created_at' => '2026-01-02 03:04:05',
            'last_used_at' => null,
            'revoked_at' => null,
        ];
    }
}
