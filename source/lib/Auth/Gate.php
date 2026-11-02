<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use PDOException;
use Pidgey\Container;
use Pidgey\Http\BaseMiddleware;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\UnauthorizedException;
use Pidgey\Infra\WallClock;
use Pidgey\Repository\UserRepository;

/**
 * Route middleware: the gate of the cookie-authenticated surface (see `api/authentication/gate.md`).
 *
 * Reads the `access_token` cookie, verifies the JWT strictly, then finds the approved user behind
 * a live session in one query and attaches it as an AuthenticatedUser. Every failure throws the
 * same uniform 401; nothing about the token or the reason is logged or returned. The
 * `Authorization` header is never read. Database errors are not caught, so they answer 500.
 */
final class Gate extends BaseMiddleware
{
    public const UNAUTHORIZED_MESSAGE = 'Invalid or expired access token';

    /**
     * Builds the gate.
     *
     * @param AccessToken    $tokens Verifies the access token.
     * @param UserRepository $users  Finds the user behind the session.
     * @param WallClock      $clock  Source of `now` for the session lookup.
     */
    public function __construct(
        private readonly AccessToken $tokens,
        private readonly UserRepository $users,
        private readonly WallClock $clock,
    ) {
    }

    /**
     * Builds the gate from the request's container; opens nothing until a request is checked.
     *
     * @param Container $container The request's container.
     *
     * @return static
     */
    public static function build(Container $container): static
    {
        return new self($container->accessToken(), $container->userRepository(), $container->wallClock());
    }

    /**
     * Authenticates the request and attaches the AuthenticatedUser.
     *
     * @param ProcessingRequest $request The request being dispatched.
     *
     * @return void
     *
     * @throws UnauthorizedException When the cookie is missing, the token is invalid or no approved
     *                               user has a live session for it.
     * @throws PDOException          When the session lookup fails.
     */
    public function processRequest(ProcessingRequest $request): void
    {
        $jwt = $request->request()->cookie(AccessTokenCookie::NAME);

        if ($jwt === null || $jwt === '') {
            throw self::unauthorized();
        }

        $claims = $this->tokens->verify($jwt);

        if ($claims === null) {
            throw self::unauthorized();
        }

        $user = $this->users->findApprovedBySession($claims->userId, $claims->sessionId, $this->clock->now());

        if ($user === null) {
            throw self::unauthorized();
        }

        $request->attach(AuthenticatedUser::fromUser($user, $claims->sessionId));
    }

    /**
     * Builds the uniform 401 shared by every gate failure.
     *
     * @return UnauthorizedException
     */
    private static function unauthorized(): UnauthorizedException
    {
        return new UnauthorizedException(self::UNAUTHORIZED_MESSAGE);
    }
}
