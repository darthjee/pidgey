<?php

declare(strict_types=1);

namespace Pidgey\Http\Handler;

use LogicException;
use PDOException;
use Pidgey\Auth\ApiTokenRevokeOutcome;
use Pidgey\Auth\ApiTokens;
use Pidgey\Auth\AuthenticatedUser;
use Pidgey\Container;
use Pidgey\Http\BadRequestException;
use Pidgey\Http\NotFoundException;
use Pidgey\Http\OperatorLog;
use Pidgey\Http\PathId;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;

/**
 * `DELETE /api-tokens/:id.json`, gated (see `api/endpoints/pidgey-admin/api-tokens.md`).
 *
 * Revokes the token and keeps the row; idempotent. An unknown, out-of-range or foreign id
 * answers `404`.
 */
final class ApiTokenHandler extends RequestHandler
{
    public const NOT_FOUND = 'API token not found';

    /**
     * Builds the handler.
     *
     * @param ApiTokens   $apiTokens   The API tokens service.
     * @param OperatorLog $operatorLog Writes the operator log line.
     */
    public function __construct(
        private readonly ApiTokens $apiTokens,
        private readonly OperatorLog $operatorLog,
    ) {
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
        return new self($container->apiTokens(), $container->operatorLog());
    }

    /**
     * Revokes one token of the caller's organization and answers `204`.
     *
     * @param ProcessingRequest $request The request being dispatched, with its AuthenticatedUser.
     *
     * @return Response
     *
     * @throws BadRequestException When the `:id` is malformed.
     * @throws NotFoundException   When no token of the organization has this id.
     * @throws LogicException      When the route's method is not DELETE.
     * @throws PDOException        When a statement fails.
     */
    protected function process(ProcessingRequest $request): Response
    {
        $id = PathId::parse($request, self::NOT_FOUND);
        $user = $request->require(AuthenticatedUser::class);

        if ($request->method() !== 'DELETE') {
            throw new LogicException('Unsupported method ' . $request->method());
        }

        $outcome = $this->apiTokens->revoke($user->organizationId(), $id);

        if ($outcome === ApiTokenRevokeOutcome::NotFound) {
            throw new NotFoundException(self::NOT_FOUND);
        }

        if ($outcome === ApiTokenRevokeOutcome::Revoked) {
            $this->operatorLog->record(OperatorLog::API_TOKEN_REVOKE, $user->id, $user->organizationId(), $id);
        }

        return new Response('', 204);
    }
}
