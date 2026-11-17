<?php

declare(strict_types=1);

namespace Pidgey\Http\Handler;

use JsonException;
use LogicException;
use PDOException;
use Pidgey\Auth\ApiTokens;
use Pidgey\Auth\AuthenticatedUser;
use Pidgey\Container;
use Pidgey\Http\ApiTokenPayload;
use Pidgey\Http\ApiTokenRules;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\OperatorLog;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;
use Pidgey\Http\ValidationFailedException;
use Pidgey\Model\ApiToken;
use Random\RandomException;

/**
 * `GET /api-tokens.json` (list) and `POST /api-tokens.json` (create), gated (see
 * `api/endpoints/pidgey-admin/api-tokens.md`).
 *
 * The organization is always the gate's. The plain token is returned once, by the create, and
 * is never logged.
 */
final class ApiTokensHandler extends RequestHandler
{
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
     * Lists the organization's tokens, or creates one.
     *
     * @param ProcessingRequest $request The request being dispatched, with its AuthenticatedUser.
     *
     * @return Response
     *
     * @throws ValidationFailedException When the create body is invalid (every error at once).
     * @throws LogicException            When the route's method is neither GET nor POST.
     * @throws RandomException           When no source of randomness is available.
     * @throws JsonException             When the scopes cannot be encoded.
     * @throws PDOException              When a statement fails.
     */
    protected function process(ProcessingRequest $request): Response
    {
        $user = $request->require(AuthenticatedUser::class);

        return match ($request->method()) {
            'GET' => $this->list($user),
            'POST' => $this->create($request, $user),
            default => throw new LogicException('Unsupported method ' . $request->method()),
        };
    }

    /**
     * Answers `200` with every token of the organization, in id order.
     *
     * @param AuthenticatedUser $user The caller.
     *
     * @return Response
     */
    private function list(AuthenticatedUser $user): Response
    {
        $items = array_map(
            static fn (ApiToken $token): ApiTokenPayload => new ApiTokenPayload($token),
            $this->apiTokens->list($user->organizationId()),
        );

        return new JsonResponse(['apiTokens' => $items], 200);
    }

    /**
     * Validates the body, creates the token and answers `201` with the item and the plain token.
     *
     * @param ProcessingRequest $request The request being dispatched.
     * @param AuthenticatedUser $user    The caller.
     *
     * @return Response
     *
     * @throws ValidationFailedException When the body is invalid.
     * @throws RandomException           When no source of randomness is available.
     * @throws JsonException             When the scopes cannot be encoded.
     * @throws PDOException              When a statement fails.
     */
    private function create(ProcessingRequest $request, AuthenticatedUser $user): Response
    {
        $fields = ApiTokenRules::forCreate($request->request()->json());
        $organizationId = $user->organizationId();
        $created = $this->apiTokens->create($organizationId, $fields['name'], $fields['scopes']);

        $this->operatorLog->record(
            OperatorLog::API_TOKEN_CREATE,
            $user->id,
            $organizationId,
            $created->apiToken->id,
        );

        return new JsonResponse(['apiToken' => new ApiTokenPayload($created->apiToken, $created->token)], 201);
    }
}
