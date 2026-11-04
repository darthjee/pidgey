<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Auth;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Auth\AccessToken;
use Pidgey\Auth\AccessTokenClaims;
use Pidgey\Auth\Base64Url;
use Pidgey\Auth\SecretKeys;
use Pidgey\Infra\WallClock;

#[CoversClass(AccessToken::class)]
#[UsesClass(AccessTokenClaims::class)]
#[UsesClass(Base64Url::class)]
#[UsesClass(SecretKeys::class)]
final class AccessTokenTest extends TestCase
{
    private const CURRENT_KEY = 'test-secret-value-not-a-real-secret-current';
    private const PREVIOUS_KEY = 'test-secret-value-not-a-real-secret-previous';
    private const OTHER_KEY = 'test-secret-value-not-a-real-secret-other';

    private const NOW = 1_800_000_000;
    private const TTL = 900;
    private const SESSION_ID = '0f8fad5b-d9cb-469f-a165-70867728950e';
    private const HEADER = '{"alg":"HS256","typ":"JWT"}';

    public function testTtlIsTheConfiguredLifetime(): void
    {
        $this->assertSame(self::TTL, self::service()->ttl());
    }

    public function testIssuedTokenVerifies(): void
    {
        $service = self::service();

        $claims = $service->verify($service->issue(42, self::SESSION_ID, 'alice', true));

        $this->assertEquals(new AccessTokenClaims(42, self::SESSION_ID, self::NOW, self::NOW + self::TTL), $claims);
    }

    public function testIssuedTokenHasTheFixedHeaderAndAllClaims(): void
    {
        $token = self::service()->issue(42, self::SESSION_ID, 'alice/ü', false);
        [$header, $payload] = explode('.', $token);

        $this->assertSame(self::HEADER, Base64Url::decode($header));
        $this->assertSame(
            '{"sub":42,"sid":"' . self::SESSION_ID . '","username":"alice/ü","isAdmin":false,'
            . '"iat":' . self::NOW . ',"exp":' . (self::NOW + self::TTL) . '}',
            Base64Url::decode($payload),
        );
    }

    public function testIssuedTokenIsSignedWithTheCurrentKeyOnly(): void
    {
        $token = self::service([self::PREVIOUS_KEY])->issue(42, self::SESSION_ID, 'alice', false);

        $this->assertNotNull(self::service()->verify($token));
        $this->assertNull(self::service([], self::PREVIOUS_KEY)->verify($token));
    }

    public function testTokenSignedWithAPreviousKeyVerifies(): void
    {
        $token = self::service([], self::PREVIOUS_KEY)->issue(7, self::SESSION_ID, 'bob', false);

        $claims = self::service([self::OTHER_KEY, self::PREVIOUS_KEY])->verify($token);

        $this->assertNotNull($claims);
        $this->assertSame(7, $claims->userId);
    }

    public function testTokenSignedWithAnUnknownKeyIsRejected(): void
    {
        $token = self::service([], self::OTHER_KEY)->issue(7, self::SESSION_ID, 'bob', false);

        $this->assertNull(self::service([self::PREVIOUS_KEY])->verify($token));
    }

    public function testUnknownClaimsAreIgnored(): void
    {
        $token = self::token(self::HEADER, self::payload(['jti' => 'x', 'nested' => ['a' => [1]]]));

        $this->assertNotNull(self::service()->verify($token));
    }

    public function testExpiryOneSecondAfterNowVerifies(): void
    {
        $token = self::token(self::HEADER, self::payload(['iat' => self::NOW - self::TTL + 1, 'exp' => self::NOW + 1]));

        $this->assertNotNull(self::service()->verify($token));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedTokens(): array
    {
        $valid = self::token(self::HEADER, self::payload([]));
        [$header, $payload, $signature] = explode('.', $valid);

        return [
            'empty' => [''],
            'one segment' => [$header],
            'two segments' => ["{$header}.{$payload}"],
            'four segments' => ["{$valid}.{$signature}"],
            'empty signature' => ["{$header}.{$payload}."],
            'empty header' => [".{$payload}.{$signature}"],
            'padding' => ["{$header}=.{$payload}.{$signature}"],
            'plus and slash' => ["{$header}.{$payload}." . strtr($signature, '-_', '+/') . '+/'],
            'non-canonical signature' => ["{$header}.{$payload}." . self::nonCanonical($signature)],
            'non-canonical payload' => ["{$header}." . self::nonCanonical($payload) . ".{$signature}"],
            'tampered signature' => ["{$header}.{$payload}." . self::flipFirst($signature)],
            'tampered payload' => [
                "{$header}." . Base64Url::encode((string) json_encode(['sub' => 2] + self::claims())) . ".{$signature}",
            ],
            'surrounding whitespace' => [" {$valid}"],
        ];
    }

    #[DataProvider('malformedTokens')]
    public function testMalformedTokensAreRejected(string $token): void
    {
        $this->assertNull(self::service()->verify($token));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function otherHeaders(): array
    {
        return [
            'alg none' => ['{"alg":"none","typ":"JWT"}'],
            'HS512' => ['{"alg":"HS512","typ":"JWT"}'],
            'extra kid' => ['{"alg":"HS256","typ":"JWT","kid":"1"}'],
            'different key order' => ['{"typ":"JWT","alg":"HS256"}'],
            'whitespace' => ['{"alg": "HS256", "typ": "JWT"}'],
            'no typ' => ['{"alg":"HS256"}'],
        ];
    }

    #[DataProvider('otherHeaders')]
    public function testAnyOtherHeaderIsRejectedEvenWhenCorrectlySigned(string $header): void
    {
        $this->assertNull(self::service()->verify(self::token($header, self::payload([]))));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPayloads(): array
    {
        $now = self::NOW;
        $sid = self::SESSION_ID;

        return [
            'not json' => ['not json'],
            'json array' => ['[1,"' . $sid . '",' . $now . ',' . ($now + 1) . ']'],
            'json list of claims' => ['[' . self::payload([]) . ']'],
            'json string' => ['"x"'],
            'json null' => ['null'],
            'too deep' => [self::payload(['deep' => json_decode(str_repeat('[', 10) . str_repeat(']', 10))])],
            'sub missing' => [self::payload([], ['sub'])],
            'sub zero' => [self::payload(['sub' => 0])],
            'sub negative' => [self::payload(['sub' => -1])],
            'sub string' => [self::payload(['sub' => '1'])],
            'sub float' => [str_replace('"sub":1,', '"sub":1.0,', self::payload([]))],
            'sub beyond int range' => [str_replace('"sub":1,', '"sub":9223372036854775808,', self::payload([]))],
            'sub null' => [self::payload(['sub' => null])],
            'sid missing' => [self::payload([], ['sid'])],
            'sid uppercase' => [self::payload(['sid' => strtoupper($sid)])],
            'sid without dashes' => [self::payload(['sid' => str_replace('-', '', $sid)])],
            'sid braces' => [self::payload(['sid' => '{' . $sid . '}'])],
            'sid trailing newline' => [self::payload(['sid' => $sid . "\n"])],
            'sid integer' => [self::payload(['sid' => 1])],
            'iat missing' => [self::payload([], ['iat'])],
            'iat string' => [self::payload(['iat' => (string) $now])],
            'iat float' => [str_replace('"iat":' . $now, '"iat":' . $now . '.0', self::payload([]))],
            'exp missing' => [self::payload([], ['exp'])],
            'exp float' => [str_replace('"exp":' . ($now + 1), '"exp":' . ($now + 1) . '.0', self::payload([]))],
            'exp beyond int range' => [
                str_replace('"exp":' . ($now + 1), '"exp":99999999999999999999', self::payload([])),
            ],
            'iat in the future' => [self::payload(['iat' => $now + 1, 'exp' => $now + 2])],
            'exp equals now' => [self::payload(['iat' => $now - 1, 'exp' => $now])],
            'exp before now' => [self::payload(['iat' => $now - 10, 'exp' => $now - 1])],
            'lifetime beyond ttl' => [self::payload(['iat' => $now - 1, 'exp' => $now - 1 + self::TTL + 1])],
            'huge exp' => [self::payload(['iat' => $now, 'exp' => PHP_INT_MAX])],
            'min iat' => [self::payload(['iat' => PHP_INT_MIN, 'exp' => $now + 1])],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function testCorrectlySignedTokensWithInvalidClaimsAreRejected(string $payload): void
    {
        $this->assertNull(self::service()->verify(self::token(self::HEADER, $payload)));
    }

    /**
     * @param list<string> $previous
     */
    private static function service(array $previous = [], string $current = self::CURRENT_KEY): AccessToken
    {
        $clock = new class implements WallClock {
            public function now(): DateTimeImmutable
            {
                return (new DateTimeImmutable('@1800000000'))->setTimezone(new DateTimeZone('UTC'));
            }
        };

        return new AccessToken(new SecretKeys($current, $previous), self::TTL, $clock);
    }

    /**
     * Builds a token over raw header and payload JSON, signed with the current key.
     */
    private static function token(string $header, string $payload): string
    {
        $signingInput = Base64Url::encode($header) . '.' . Base64Url::encode($payload);
        $signature = Base64Url::encode(hash_hmac('sha256', $signingInput, self::CURRENT_KEY, true));

        return "{$signingInput}.{$signature}";
    }

    /**
     * @return array<string, mixed>
     */
    private static function claims(): array
    {
        return ['sub' => 1, 'sid' => self::SESSION_ID, 'iat' => self::NOW, 'exp' => self::NOW + 1];
    }

    /**
     * @param array<string, mixed> $overrides
     * @param list<string>         $without
     */
    private static function payload(array $overrides, array $without = []): string
    {
        $claims = array_diff_key(array_merge(self::claims(), $overrides), array_flip($without));

        return (string) json_encode($claims);
    }

    /**
     * Changes the unused trailing bits of a base64url segment: same bytes, other encoding.
     */
    private static function nonCanonical(string $segment): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
        $last = strpos($alphabet, $segment[-1]);

        return substr($segment, 0, -1) . $alphabet[(int) $last + 1];
    }

    private static function flipFirst(string $segment): string
    {
        return ($segment[0] === 'A' ? 'B' : 'A') . substr($segment, 1);
    }
}
