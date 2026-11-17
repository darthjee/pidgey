<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\ApiTokenPayload;
use Pidgey\Http\Timestamp;
use Pidgey\Model\ApiToken;

#[CoversClass(ApiTokenPayload::class)]
#[UsesClass(Timestamp::class)]
final class ApiTokenPayloadTest extends TestCase
{
    public function testListShape(): void
    {
        $this->assertSame(
            [
                'id' => 5,
                'name' => 'Shop',
                'tokenPrefix' => 'pdg_',
                'scopes' => ['send'],
                'createdAt' => '2026-10-17T12:00:00.000Z',
                'lastUsedAt' => null,
                'revokedAt' => null,
            ],
            (new ApiTokenPayload($this->apiToken()))->jsonSerialize(),
        );
    }

    public function testCreateShapeAddsTheToken(): void
    {
        $item = (new ApiTokenPayload($this->apiToken(), 'pdg_secret'))->jsonSerialize();

        $this->assertSame('pdg_secret', $item['token']);
        $this->assertArrayNotHasKey('tokenHash', $item);
        $this->assertArrayNotHasKey('organizationId', $item);
    }

    public function testSerializesDates(): void
    {
        $date = new DateTimeImmutable('2026-10-20 09:30:00', new DateTimeZone('UTC'));
        $item = (new ApiTokenPayload($this->apiToken($date, $date)))->jsonSerialize();

        $this->assertSame('2026-10-20T09:30:00.000Z', $item['lastUsedAt']);
        $this->assertSame('2026-10-20T09:30:00.000Z', $item['revokedAt']);
    }

    public function testDebugInfoOmitsTheToken(): void
    {
        $payload = new ApiTokenPayload($this->apiToken(), 'pdg_secret');

        $this->assertArrayNotHasKey('token', $payload->__debugInfo());
        $this->assertStringNotContainsString('pdg_secret', print_r($payload, true));
    }

    private function apiToken(?DateTimeImmutable $lastUsedAt = null, ?DateTimeImmutable $revokedAt = null): ApiToken
    {
        return new ApiToken(
            5,
            1,
            'Shop',
            'pdg_',
            str_repeat('a', 64),
            ['send'],
            new DateTimeImmutable('2026-10-17 12:00:00', new DateTimeZone('UTC')),
            $lastUsedAt,
            $revokedAt,
        );
    }
}
