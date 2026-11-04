<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Auth;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Auth\ActiveSession;
use Pidgey\Model\RefreshToken;
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;

#[CoversClass(ActiveSession::class)]
#[UsesClass(RefreshToken::class)]
#[UsesClass(User::class)]
final class ActiveSessionTest extends TestCase
{
    private const HASH = 'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90';
    private const DIGEST = '$2y$10$digest';
    private const EMAIL = 'bob@example.com';
    private const SESSION = '6f1c2a4e-8b3d-4c5f-9a7e-0d1b2c3e4f5a';

    public function testHoldsItsValues(): void
    {
        $session = $this->session();

        $this->assertSame(11, $session->token->id);
        $this->assertSame(7, $session->user->id);
    }

    public function testDebugInfoRedactsTheDigests(): void
    {
        $this->assertSame(
            [
                'token' => ['id' => 11, 'userId' => 7, 'sessionUuid' => self::SESSION],
                'user' => ['id' => 7, 'username' => 'bob'],
            ],
            $this->session()->__debugInfo(),
        );
    }

    public function testPrintRShowsNeitherDigestNorEmail(): void
    {
        $output = print_r($this->session(), true);

        $this->assertStringNotContainsString(self::HASH, $output);
        $this->assertStringNotContainsString(self::DIGEST, $output);
        $this->assertStringNotContainsString(self::EMAIL, $output);
        $this->assertStringContainsString(self::SESSION, $output);
    }

    private function session(): ActiveSession
    {
        $now = new DateTimeImmutable('2026-10-25T12:00:00Z');
        $user = new User(7, 3, 'bob', self::EMAIL, self::DIGEST, false, UserStatus::Approved, $now, $now);
        $token = new RefreshToken(11, self::HASH, 7, $now, $now->modify('+1 day'), null, null, self::SESSION, $now);

        return new ActiveSession($token, $user);
    }
}
