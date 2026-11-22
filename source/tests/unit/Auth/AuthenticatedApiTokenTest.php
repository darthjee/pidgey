<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Auth;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Auth\AuthenticatedApiToken;
use Pidgey\Auth\Caller;
use Pidgey\Model\ApiToken;

#[CoversClass(AuthenticatedApiToken::class)]
#[UsesClass(ApiToken::class)]
final class AuthenticatedApiTokenTest extends TestCase
{
    public function testItIsACallerOfTheTokenOrganization(): void
    {
        $token = new AuthenticatedApiToken(3, 9, ['send']);

        $this->assertInstanceOf(Caller::class, $token);
        $this->assertSame(9, $token->organizationId());
        $this->assertSame(3, $token->id);
    }

    public function testFromTokenCopiesTheRowValues(): void
    {
        $now = new DateTimeImmutable('2026-11-22 10:00:00');
        $row = new ApiToken(4, 8, 'CI', 'pdg_', str_repeat('a', 64), ['send', 'read'], $now, null, null);

        $this->assertEquals(new AuthenticatedApiToken(4, 8, ['send', 'read']), AuthenticatedApiToken::fromToken($row));
    }

    public function testHasScopeMatchesGrantedScopesOnly(): void
    {
        $token = new AuthenticatedApiToken(1, 1, ['send']);

        $this->assertTrue($token->hasScope('send'));
        $this->assertFalse($token->hasScope('admin'));
        $this->assertFalse((new AuthenticatedApiToken(1, 1, []))->hasScope('send'));
    }
}
