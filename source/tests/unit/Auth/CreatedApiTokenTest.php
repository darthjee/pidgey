<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Auth;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Auth\CreatedApiToken;
use Pidgey\Model\ApiToken;

#[CoversClass(CreatedApiToken::class)]
final class CreatedApiTokenTest extends TestCase
{
    public function testExposesBothValues(): void
    {
        $apiToken = $this->apiToken();
        $created = new CreatedApiToken($apiToken, 'pdg_secret');

        $this->assertSame($apiToken, $created->apiToken);
        $this->assertSame('pdg_secret', $created->token);
    }

    public function testDebugInfoOmitsThePlainToken(): void
    {
        $created = new CreatedApiToken($this->apiToken(), 'pdg_secret');

        $this->assertStringNotContainsString('pdg_secret', print_r($created, true));
        $this->assertArrayNotHasKey('token', $created->__debugInfo());
    }

    private function apiToken(): ApiToken
    {
        return new ApiToken(1, 1, 'CI', 'pdg_', str_repeat('a', 64), ['send'], new DateTimeImmutable(), null, null);
    }
}
