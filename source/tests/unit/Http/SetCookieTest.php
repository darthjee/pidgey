<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\Response;
use Pidgey\Http\SetCookie;

#[CoversClass(SetCookie::class)]
#[UsesClass(Response::class)]
final class SetCookieTest extends TestCase
{
    public function testWritesEveryAttributeInOrder(): void
    {
        $cookie = new SetCookie(
            'name',
            'value',
            path: '/',
            maxAge: 60,
            expires: 'Thu, 01 Jan 1970 00:00:00 GMT',
            sameSite: 'Strict',
        );

        $this->assertSame(
            'Set-Cookie: name=value; HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=60; '
            . 'Expires=Thu, 01 Jan 1970 00:00:00 GMT',
            $cookie->line(),
        );
    }

    public function testWritesOnlyTheAttributesThatAreSet(): void
    {
        $cookie = new SetCookie('name', '', httpOnly: false, secure: false);

        $this->assertSame('Set-Cookie: name=', $cookie->line());
    }

    public function testDefaultsToHttpOnlyAndSecure(): void
    {
        $this->assertSame('Set-Cookie: a=b; HttpOnly; Secure; Max-Age=0', (new SetCookie('a', 'b', maxAge: 0))->line());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function forbiddenCharacters(): array
    {
        return [
            'CR' => ["\r"],
            'LF' => ["\n"],
            'semicolon' => [';'],
            'comma' => [','],
        ];
    }

    #[DataProvider('forbiddenCharacters')]
    public function testRejectsForbiddenCharactersInTheName(string $character): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cookie names must not contain CR, LF, ";" or ",".');

        new SetCookie("na{$character}me", 'value');
    }

    #[DataProvider('forbiddenCharacters')]
    public function testRejectsForbiddenCharactersInTheValueWithoutEchoingIt(string $character): void
    {
        $value = "secret-cookie-value{$character}more";

        try {
            new SetCookie('name', $value);
            $this->fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException $error) {
            $this->assertSame('Cookie values must not contain CR, LF, ";" or ",".', $error->getMessage());
            $this->assertStringNotContainsString('secret-cookie-value', $error->getMessage());
        }
    }

    public function testSeveralCookiesOnOneResponseAllSurvive(): void
    {
        $response = new Response('', 204, ['Cache-Control: no-store']);

        (new SetCookie('first', '1', path: '/'))->applyTo($response);
        (new SetCookie('second', '2', path: '/'))->applyTo($response);

        $this->assertSame(
            [
                'Cache-Control: no-store',
                'Set-Cookie: first=1; HttpOnly; Secure; Path=/',
                'Set-Cookie: second=2; HttpOnly; Secure; Path=/',
            ],
            $response->headers(),
        );
    }
}
