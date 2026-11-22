<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use PDOException;
use Pidgey\Container;
use Pidgey\Http\BaseMiddleware;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\UnauthorizedException;
use Pidgey\Infra\WallClock;
use Pidgey\Repository\ApiTokenRepository;

/**
 * Route middleware: authenticates the Bearer API token of a `/v1` request (see
 * `api/authentication/api-tokens.md`).
 *
 * Hashes the presented token and finds a non-revoked row whose organization has an `approved`
 * user, then stamps `last_used_at` and attaches an AuthenticatedApiToken. Every failure throws the
 * same uniform 401; nothing about the token or the reason is logged or returned. The
 * `access_token` cookie is never read. Database errors are not caught, so they answer 500.
 */
final class ApiTokenAuth extends BaseMiddleware
{
    public const UNAUTHORIZED_MESSAGE = 'Invalid or revoked API token';

    private const BEARER = '/\ABearer ([^\s]+)\z/i';

    /**
     * Builds the middleware.
     *
     * @param ApiTokenRepository $tokens Finds the token and stamps its use.
     * @param WallClock          $clock  Source of `now` for `last_used_at`.
     */
    public function __construct(private readonly ApiTokenRepository $tokens, private readonly WallClock $clock)
    {
    }

    /**
     * Builds the middleware from the request's container; opens nothing until a request is checked.
     *
     * @param Container $container The request's container.
     *
     * @return static
     */
    public static function build(Container $container): static
    {
        return new self($container->apiTokenRepository(), $container->wallClock());
    }

    /**
     * Extracts the token from an `Authorization` header value.
     *
     * Only `Bearer <token>` (one space, a token without whitespace) is well formed.
     *
     * @param string|null $header The raw header value, or null when it was not sent.
     *
     * @return string|null Null when the header is missing or malformed.
     */
    public static function bearerToken(?string $header): ?string
    {
        if ($header === null || preg_match(self::BEARER, $header, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * Authenticates the request, stamps `last_used_at` and attaches the AuthenticatedApiToken.
     *
     * @param ProcessingRequest $request The request being dispatched.
     *
     * @return void
     *
     * @throws UnauthorizedException When the header is missing or malformed, or the token is
     *                               unknown, revoked or its organization has no approved user.
     * @throws PDOException          When the lookup or the update fails.
     */
    public function processRequest(ProcessingRequest $request): void
    {
        $plain = self::bearerToken($request->request()->header('Authorization'));

        if ($plain === null) {
            throw self::unauthorized();
        }

        $token = $this->tokens->findByTokenHashWithApprovedUser(ApiTokenValue::hash($plain));

        if ($token === null || $token->revokedAt !== null) {
            throw self::unauthorized();
        }

        $this->tokens->touchLastUsedAt($token->id, $this->clock->now());
        $request->attach(AuthenticatedApiToken::fromToken($token));
    }

    /**
     * Builds the uniform 401 shared by every failure.
     *
     * @return UnauthorizedException
     */
    private static function unauthorized(): UnauthorizedException
    {
        return new UnauthorizedException(self::UNAUTHORIZED_MESSAGE);
    }
}
