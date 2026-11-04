<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Auth;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Auth\AccessTokenCookie;
use Pidgey\Auth\SessionResponse;
use Pidgey\Auth\SessionTokens;
use Pidgey\Auth\UserPayload;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\Response;
use Pidgey\Http\SetCookie;
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;

#[CoversClass(SessionResponse::class)]
#[UsesClass(AccessTokenCookie::class)]
#[UsesClass(SessionTokens::class)]
#[UsesClass(UserPayload::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(Response::class)]
#[UsesClass(SetCookie::class)]
#[UsesClass(User::class)]
final class SessionResponseTest extends TestCase
{
    private const REFRESH = 'plain-refresh-token-value';
    private const ACCESS = 'header.payload.signature';
    private const SESSION = '6f1c2a4e-8b3d-4c5f-9a7e-0d1b2c3e4f5a';
    private const DIGEST = '$2y$10$digest';

    public function testIsA201(): void
    {
        $this->assertSame(201, $this->response()->status());
    }

    public function testBodyHasTheUserPayloadAndTheRefreshToken(): void
    {
        $body = json_decode($this->response()->body(), true, 8, JSON_THROW_ON_ERROR);

        $this->assertSame(
            [
                'user' => ['id' => 7, 'username' => 'bob', 'email' => 'bob@example.com', 'isAdmin' => true],
                'refreshToken' => self::REFRESH,
            ],
            $body,
        );
    }

    public function testBodyCarriesNeitherTheAccessTokenNorTheDigestNorTheStatus(): void
    {
        $body = $this->response()->body();

        $this->assertStringNotContainsString(self::ACCESS, $body);
        $this->assertStringNotContainsString(self::DIGEST, $body);
        $this->assertStringNotContainsString('status', $body);
        $this->assertStringNotContainsString('pending', $body);
    }

    public function testSetsOneAccessTokenCookieWithTheGivenTtl(): void
    {
        $cookies = array_values(array_filter(
            $this->response()->headers(),
            static fn (string $line): bool => str_starts_with($line, 'Set-Cookie:'),
        ));

        $this->assertSame(
            ['Set-Cookie: access_token=' . self::ACCESS . '; HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=60'],
            $cookies,
        );
    }

    public function testIsJson(): void
    {
        $this->assertContains('Content-Type: application/json', $this->response()->headers());
    }

    private function response(): Response
    {
        $now = new DateTimeImmutable('2026-10-25T12:00:00Z');
        $user = new User(7, 3, 'bob', 'bob@example.com', self::DIGEST, true, UserStatus::Pending, $now, $now);

        return SessionResponse::created(new SessionTokens(self::REFRESH, self::ACCESS, self::SESSION, $user), 60);
    }
}
