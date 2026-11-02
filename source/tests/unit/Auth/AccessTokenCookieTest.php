<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Auth\AccessTokenCookie;
use Pidgey\Http\Response;
use Pidgey\Http\SetCookie;

#[CoversClass(AccessTokenCookie::class)]
#[UsesClass(Response::class)]
#[UsesClass(SetCookie::class)]
final class AccessTokenCookieTest extends TestCase
{
    private const JWT = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjF9.c2lnbmF0dXJl';

    public function testSetsTheAccessToken(): void
    {
        $this->assertSame(
            'Set-Cookie: access_token=' . self::JWT . '; HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=900',
            AccessTokenCookie::set(self::JWT, 900)->line(),
        );
    }

    public function testClearsTheAccessToken(): void
    {
        $this->assertSame(
            'Set-Cookie: access_token=; HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=0; '
            . 'Expires=Thu, 01 Jan 1970 00:00:00 GMT',
            AccessTokenCookie::clear()->line(),
        );
    }

    public function testNeverWritesADomain(): void
    {
        $this->assertStringNotContainsStringIgnoringCase('domain', AccessTokenCookie::set(self::JWT, 60)->line());
        $this->assertStringNotContainsStringIgnoringCase('domain', AccessTokenCookie::clear()->line());
    }

    public function testSetAndClearCoexistOnOneResponse(): void
    {
        $response = new Response('', 204);

        AccessTokenCookie::clear()->applyTo($response);
        AccessTokenCookie::set(self::JWT, 900)->applyTo($response);

        $this->assertSame(
            [AccessTokenCookie::clear()->line(), AccessTokenCookie::set(self::JWT, 900)->line()],
            $response->headers(),
        );
    }
}
