<?php

declare(strict_types=1);

namespace Pidgey\Http\Handler;

use LogicException;
use Pidgey\Auth\AuthenticatedUser;
use Pidgey\Container;
use Pidgey\Http\BadRequestException;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\NotFoundException;
use Pidgey\Http\OperatorLog;
use Pidgey\Http\PathId;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;
use Pidgey\Http\SmtpConnectionPayload;
use Pidgey\Infra\Transaction;
use Pidgey\Model\SmtpConnection;
use Pidgey\Repository\OrganizationRepository;
use Pidgey\Repository\SmtpConnectionRepository;
use Throwable;

/**
 * Shared flow of `POST /smtp-connections/:id/activate.json` and `…/deactivate.json`, gated.
 *
 * In one transaction: lock the organization row, lock the target connection row (`404` when
 * unknown, foreign or deleted meanwhile), change `active`, reload; then answer `200` with the
 * item and write one operator log line. The body is ignored. The organization lock serializes
 * every activation and deactivation of the organization; the row lock is a locking (current)
 * read, so a DELETE committed after the transaction's snapshot is seen, and it keeps the row
 * from being deleted until the change commits. The reload is a locking read too, so the item
 * reflects the committed row even when the change was a no-op (already active or inactive).
 */
abstract class SmtpConnectionStateHandler extends RequestHandler
{
    /**
     * Builds the handler.
     *
     * @param OrganizationRepository   $organizations The `organizations` repository (row lock).
     * @param SmtpConnectionRepository $connections   The `smtp_connections` repository.
     * @param Transaction              $transaction   Runs the change atomically.
     * @param OperatorLog              $operatorLog   Writes the operator log line.
     */
    final public function __construct(
        protected readonly OrganizationRepository $organizations,
        protected readonly SmtpConnectionRepository $connections,
        private readonly Transaction $transaction,
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
        return new static(
            $container->organizationRepository(),
            $container->smtpConnectionRepository(),
            $container->transaction(),
            $container->operatorLog(),
        );
    }

    /**
     * Changes the connection's `active` flag and answers `200` with the reloaded item.
     *
     * @param ProcessingRequest $request The request being dispatched, with its AuthenticatedUser.
     *
     * @return Response
     *
     * @throws BadRequestException When the `:id` is malformed.
     * @throws NotFoundException   When no connection of the organization has this id.
     * @throws LogicException     When the caller's organization does not exist (answered `500`).
     * @throws Throwable           When a step fails (rolled back, answered `500`).
     */
    protected function process(ProcessingRequest $request): Response
    {
        $id = PathId::parse($request, SmtpConnectionHandler::NOT_FOUND);
        $user = $request->require(AuthenticatedUser::class);
        $organizationId = $user->organizationId();

        $connection = $this->transaction->run(function () use ($organizationId, $id): SmtpConnection {
            if (!$this->organizations->lockForUpdate($organizationId)) {
                throw new LogicException("Organization {$organizationId} of the authenticated user does not exist.");
            }

            if ($this->connections->lockInOrganization($organizationId, $id) === null) {
                throw new NotFoundException(SmtpConnectionHandler::NOT_FOUND);
            }

            $this->apply($organizationId, $id);

            return $this->connections->lockInOrganization($organizationId, $id)
                ?? throw new LogicException("SMTP connection {$id} vanished while locked.");
        });

        $this->operatorLog->record($this->action(), $user->id, $organizationId, $id);

        return new JsonResponse(['smtpConnection' => new SmtpConnectionPayload($connection)], 200);
    }

    /**
     * Changes the `active` flag; runs under the organization lock.
     *
     * @param integer $organizationId The caller's organization.
     * @param integer $id             Connection id, known to belong to the organization.
     *
     * @return void
     */
    abstract protected function apply(int $organizationId, int $id): void;

    /**
     * Returns the operator log action name.
     *
     * @return string
     */
    abstract protected function action(): string;
}
