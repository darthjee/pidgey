<?php

declare(strict_types=1);

namespace Pidgey\Http\Handler;

use Pidgey\Auth\ActiveSession;
use Pidgey\Auth\Sessions;
use Pidgey\Container;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;
use Pidgey\Http\ValidationFailedException;
use Pidgey\Http\Validator;
use Pidgey\Model\RevokedReason;
use Pidgey\Model\UserStatus;

/**
 * `POST /auth/status.json` (public, ungated): a passive "am I logged in?" check
 * (see `api/endpoints/auth/session.md#post-authstatusjson`).
 *
 * It never rotates, never runs replay detection and never sets or clears the cookie. A denied
 * user gets the anonymous answer byte for byte (their presented token is revoked), so status
 * cannot tell a denied user from an unknown token. The request body is never logged.
 */
final class StatusHandler extends RequestHandler
{
    /** The answer for no active session, and for a denied user. */
    private const ANONYMOUS = ['loggedIn' => false, 'isAdmin' => false];

    /** The answer for a user waiting for approval (the session is kept). */
    private const PENDING = ['loggedIn' => false, 'isAdmin' => false, 'status' => 'pending'];

    /**
     * Builds the handler.
     *
     * @param Sessions $sessions Looks the token up passively.
     */
    public function __construct(private readonly Sessions $sessions)
    {
    }

    /**
     * Builds the handler from the request's container.
     *
     * @param Container $container The request's container.
     *
     * @return static
     */
    public static function build(Container $container): static
    {
        return new self($container->sessions());
    }

    /**
     * Validates the body, then answers `201` with the session's status.
     *
     * @param ProcessingRequest $request The request being dispatched.
     *
     * @return Response
     *
     * @throws ValidationFailedException When `refreshToken` is missing, empty or not a string.
     */
    protected function process(ProcessingRequest $request): Response
    {
        $validator = new Validator($request->request()->json());
        $validator->string('refreshToken')->notEmpty();
        $fields = $validator->validated();

        $session = $this->sessions->findActive($fields['refreshToken']);

        return new JsonResponse($session === null ? self::ANONYMOUS : $this->answer($session), 201);
    }

    /**
     * Maps an active session to its answer, revoking the token of a denied user.
     *
     * @param ActiveSession $session The active token and its user.
     *
     * @return array<string, boolean|string>
     */
    private function answer(ActiveSession $session): array
    {
        return match ($session->user->status) {
            UserStatus::Approved => ['loggedIn' => true, 'isAdmin' => $session->user->isAdmin],
            UserStatus::Pending => self::PENDING,
            UserStatus::Denied => $this->deny($session),
        };
    }

    /**
     * Revokes a denied user's presented token and returns the anonymous answer.
     *
     * @param ActiveSession $session The active token and its denied user.
     *
     * @return array<string, boolean>
     */
    private function deny(ActiveSession $session): array
    {
        $this->sessions->revoke($session->token, RevokedReason::AdminDenied);

        return self::ANONYMOUS;
    }
}
