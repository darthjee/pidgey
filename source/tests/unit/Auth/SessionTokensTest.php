<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Auth;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Auth\SessionTokens;
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;

#[CoversClass(SessionTokens::class)]
#[UsesClass(User::class)]
final class SessionTokensTest extends TestCase
{
    private const REFRESH = 'plain-refresh-token-value';
    private const ACCESS = 'header.payload.signature';
    private const SESSION = '6f1c2a4e-8b3d-4c5f-9a7e-0d1b2c3e4f5a';

    public function testHoldsItsValues(): void
    {
        $tokens = $this->tokens();

        $this->assertSame(self::REFRESH, $tokens->refreshToken);
        $this->assertSame(self::ACCESS, $tokens->accessToken);
        $this->assertSame(self::SESSION, $tokens->sessionUuid);
        $this->assertSame(7, $tokens->user->id);
    }

    public function testDebugInfoRedactsBothTokens(): void
    {
        $debug = $this->tokens()->__debugInfo();

        $this->assertNotSame(self::REFRESH, $debug['refreshToken']);
        $this->assertNotSame(self::ACCESS, $debug['accessToken']);
        $this->assertSame(self::SESSION, $debug['sessionUuid']);
    }

    public function testPrintRShowsNeitherToken(): void
    {
        $output = print_r($this->tokens(), true);

        $this->assertStringNotContainsString(self::REFRESH, $output);
        $this->assertStringNotContainsString(self::ACCESS, $output);
        $this->assertStringContainsString(self::SESSION, $output);
    }

    private function tokens(): SessionTokens
    {
        $now = new DateTimeImmutable('2026-10-25T12:00:00Z');
        $user = new User(7, 3, 'bob', 'bob@example.com', '$2y$10$digest', false, UserStatus::Approved, $now, $now);

        return new SessionTokens(self::REFRESH, self::ACCESS, self::SESSION, $user);
    }
}
