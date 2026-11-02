<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use Pidgey\Infra\ConfigValidator;
use Pidgey\Infra\WallClock;
use SensitiveParameter;
use stdClass;

/**
 * Issues and strictly verifies the HS256 access-token JWT (see `api/authentication/access-token.md`).
 *
 * Hand-rolled on purpose: one fixed header, one algorithm, constant-time signature comparison.
 * Verification never says why a token was refused, and never logs it.
 */
final class AccessToken
{
    public const DEFAULT_TTL = ConfigValidator::DEFAULT_ACCESS_TOKEN_TTL;

    private const HEADER = '{"alg":"HS256","typ":"JWT"}';

    private const HMAC_ALGORITHM = 'sha256';

    private const PAYLOAD_MAX_DEPTH = 8;

    private const SESSION_ID_PATTERN = '/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/';

    /**
     * Builds the token service.
     *
     * @param SecretKeys $keys  Signing and verification keys.
     * @param integer    $ttl   Token lifetime in seconds (`ACCESS_TOKEN_TTL`).
     * @param WallClock  $clock Source of `now`, in UTC.
     */
    public function __construct(
        private readonly SecretKeys $keys,
        private readonly int $ttl,
        private readonly WallClock $clock,
    ) {
    }

    /**
     * Issues a token signed with the current key, valid for the TTL from now.
     *
     * @param integer $userId    The user id (`sub`).
     * @param string  $sessionId The session UUID (`sid`).
     * @param string  $username  Informational `username` claim.
     * @param boolean $isAdmin   Informational `isAdmin` claim.
     *
     * @return string The JWT: three base64url segments.
     */
    public function issue(int $userId, string $sessionId, string $username, bool $isAdmin): string
    {
        $issuedAt = $this->now();
        $payload = json_encode(
            [
                'sub' => $userId,
                'sid' => $sessionId,
                'username' => $username,
                'isAdmin' => $isAdmin,
                'iat' => $issuedAt,
                'exp' => $issuedAt + $this->ttl,
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
        $signingInput = Base64Url::encode(self::HEADER) . '.' . Base64Url::encode($payload);

        return $signingInput . '.' . self::sign($signingInput, $this->keys->current);
    }

    /**
     * Verifies a token with the strict rules and returns its trusted claims.
     *
     * @param string $token The JWT as received.
     *
     * @return AccessTokenClaims|null The claims, or null when any rule fails.
     */
    public function verify(#[SensitiveParameter] string $token): ?AccessTokenClaims
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            return null;
        }

        foreach ($segments as $segment) {
            if (Base64Url::decode($segment) === null) {
                return null;
            }
        }

        [$header, $payload, $signature] = $segments;

        if ($header !== Base64Url::encode(self::HEADER)) {
            return null;
        }

        if (!$this->signatureMatches("{$header}.{$payload}", $signature)) {
            return null;
        }

        return $this->claims((string) Base64Url::decode($payload));
    }

    /**
     * Computes the base64url HMAC-SHA256 signature of the signing input.
     *
     * @param string $signingInput The `<header>.<payload>` segments as received.
     * @param string $key          The key.
     *
     * @return string
     */
    private static function sign(string $signingInput, #[SensitiveParameter] string $key): string
    {
        return Base64Url::encode(hash_hmac(self::HMAC_ALGORITHM, $signingInput, $key, true));
    }

    /**
     * Tells whether the signature matches one of the verification keys, in constant time per key.
     *
     * @param string $signingInput The `<header>.<payload>` segments as received.
     * @param string $signature    The signature segment as received.
     *
     * @return boolean
     */
    private function signatureMatches(string $signingInput, #[SensitiveParameter] string $signature): bool
    {
        foreach ($this->keys->verificationKeys() as $key) {
            if (hash_equals(self::sign($signingInput, $key), $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Decodes the payload of a correctly signed token and checks its claims.
     *
     * @param string $json The decoded payload segment.
     *
     * @return AccessTokenClaims|null
     */
    private function claims(string $json): ?AccessTokenClaims
    {
        $payload = json_decode($json, false, self::PAYLOAD_MAX_DEPTH, JSON_BIGINT_AS_STRING);

        if (!$payload instanceof stdClass) {
            return null;
        }

        $claims = get_object_vars($payload);
        $userId = $claims['sub'] ?? null;
        $sessionId = $claims['sid'] ?? null;
        $issuedAt = $claims['iat'] ?? null;
        $expiresAt = $claims['exp'] ?? null;

        if (
            !is_int($userId)
            || $userId < 1
            || !is_string($sessionId)
            || preg_match(self::SESSION_ID_PATTERN, $sessionId) !== 1
            || !is_int($issuedAt)
            || !is_int($expiresAt)
            || !$this->isWithinLifetime($issuedAt, $expiresAt)
        ) {
            return null;
        }

        return new AccessTokenClaims($userId, $sessionId, $issuedAt, $expiresAt);
    }

    /**
     * Checks `iat <= now`, `exp > now` (no leeway) and `exp - iat <= ttl`.
     *
     * @param integer $issuedAt  The `iat` claim.
     * @param integer $expiresAt The `exp` claim.
     *
     * @return boolean
     */
    private function isWithinLifetime(int $issuedAt, int $expiresAt): bool
    {
        $now = $this->now();

        // `iat + ttl` rather than `exp - iat`: once `iat <= now` holds, the sum cannot overflow.
        return $issuedAt <= $now && $expiresAt > $now && $expiresAt <= $issuedAt + $this->ttl;
    }

    /**
     * Returns the current Unix time.
     *
     * @return integer
     */
    private function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }
}
