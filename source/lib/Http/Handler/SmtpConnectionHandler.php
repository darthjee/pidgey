<?php

declare(strict_types=1);

namespace Pidgey\Http\Handler;

use LogicException;
use Pidgey\Auth\AuthenticatedUser;
use Pidgey\Container;
use Pidgey\Crypto\PasswordCipher;
use Pidgey\Http\BadRequestException;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\NotFoundException;
use Pidgey\Http\OperatorLog;
use Pidgey\Http\PathId;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;
use Pidgey\Http\SmtpConnectionChange;
use Pidgey\Http\SmtpConnectionPayload;
use Pidgey\Http\SmtpConnectionRules;
use Pidgey\Http\ValidationFailedException;
use Pidgey\Infra\Transaction;
use Pidgey\Model\SmtpConnection;
use Pidgey\Repository\SmtpConnectionRepository;
use Throwable;

/**
 * `GET`, `PATCH` and `DELETE /smtp-connections/:id.json`, gated (see
 * `api/endpoints/pidgey-admin/smtp-connections.md`).
 *
 * The `:id` is parsed first; an unknown, out-of-range or foreign id answers `404`. The update
 * locks the connection row, validates the merged state, never writes `active` and keeps the
 * stored password bytes unless a new password is sent or the password is cleared.
 */
final class SmtpConnectionHandler extends RequestHandler
{
    public const NOT_FOUND = 'SMTP connection not found';

    /**
     * Builds the handler.
     *
     * @param SmtpConnectionRepository $connections The `smtp_connections` repository.
     * @param Transaction              $transaction Runs the update atomically.
     * @param PasswordCipher           $cipher      Encrypts a new SMTP password.
     * @param OperatorLog              $operatorLog Writes the operator log line.
     */
    public function __construct(
        private readonly SmtpConnectionRepository $connections,
        private readonly Transaction $transaction,
        private readonly PasswordCipher $cipher,
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
        return new self(
            $container->smtpConnectionRepository(),
            $container->transaction(),
            $container->passwordCipher(),
            $container->operatorLog(),
        );
    }

    /**
     * Reads, updates or deletes one connection of the caller's organization.
     *
     * @param ProcessingRequest $request The request being dispatched, with its AuthenticatedUser.
     *
     * @return Response
     *
     * @throws BadRequestException       When the `:id` is malformed.
     * @throws NotFoundException         When no connection of the organization has this id.
     * @throws ValidationFailedException When the update body is invalid (every error at once).
     * @throws LogicException            When the route's method is not GET, PATCH or DELETE.
     * @throws Throwable                 When the update fails (rolled back, answered `500`).
     */
    protected function process(ProcessingRequest $request): Response
    {
        $id = PathId::parse($request, self::NOT_FOUND);
        $user = $request->require(AuthenticatedUser::class);

        return match ($request->method()) {
            'GET' => $this->read($user, $id),
            'PATCH' => $this->update($request, $user, $id),
            'DELETE' => $this->delete($user, $id),
            default => throw new LogicException('Unsupported method ' . $request->method()),
        };
    }

    /**
     * Answers `200` with the connection.
     *
     * @param AuthenticatedUser $user The caller.
     * @param integer           $id   Connection id.
     *
     * @return Response
     *
     * @throws NotFoundException When no connection of the organization has this id.
     */
    private function read(AuthenticatedUser $user, int $id): Response
    {
        $connection = $this->connections->findInOrganization($user->organizationId(), $id)
            ?? throw new NotFoundException(self::NOT_FOUND);

        return self::item($connection);
    }

    /**
     * Updates the connection under its row lock and answers `200` with the reloaded item.
     *
     * Logs `smtp-connection-update` with the changed field names, only when a field changed.
     *
     * @param ProcessingRequest $request The request being dispatched.
     * @param AuthenticatedUser $user    The caller.
     * @param integer           $id      Connection id.
     *
     * @return Response
     *
     * @throws NotFoundException         When no connection of the organization has this id.
     * @throws ValidationFailedException When the body or the merged state is invalid.
     * @throws Throwable                 When a step of the update fails.
     */
    private function update(ProcessingRequest $request, AuthenticatedUser $user, int $id): Response
    {
        $body = $request->request()->json();
        $organizationId = $user->organizationId();

        /** @var array{SmtpConnection, SmtpConnectionChange} $result */
        $result = $this->transaction->run(function () use ($body, $organizationId, $id): array {
            $stored = $this->connections->lockInOrganization($organizationId, $id)
                ?? throw new NotFoundException(self::NOT_FOUND);
            $change = SmtpConnectionRules::forUpdate($body, $stored);

            if (!$change->hasChanges()) {
                return [$stored, $change];
            }

            $password = $change->password();
            $this->connections->update(
                $change->merged,
                $change->passwordChanged(),
                $password === null ? null : $this->cipher->encrypt($password, $organizationId, $id),
            );

            $reloaded = $this->connections->findInOrganization($organizationId, $id)
                ?? throw new LogicException("SMTP connection {$id} vanished during its update.");

            return [$reloaded, $change];
        });
        [$connection, $change] = $result;

        if ($change->hasChanges()) {
            $this->operatorLog->record(
                OperatorLog::SMTP_CONNECTION_UPDATE,
                $user->id,
                $organizationId,
                $id,
                $change->changedFields,
            );
        }

        return self::item($connection);
    }

    /**
     * Deletes the connection (active or not) and answers `204`.
     *
     * @param AuthenticatedUser $user The caller.
     * @param integer           $id   Connection id.
     *
     * @return Response
     *
     * @throws NotFoundException When no connection of the organization has this id.
     */
    private function delete(AuthenticatedUser $user, int $id): Response
    {
        if (!$this->connections->delete($user->organizationId(), $id)) {
            throw new NotFoundException(self::NOT_FOUND);
        }

        $this->operatorLog->record(OperatorLog::SMTP_CONNECTION_DELETE, $user->id, $user->organizationId(), $id);

        return new Response('', 204);
    }

    /**
     * Builds the `200 { "smtpConnection": … }` answer.
     *
     * @param SmtpConnection $connection The connection.
     *
     * @return Response
     */
    private static function item(SmtpConnection $connection): Response
    {
        return new JsonResponse(['smtpConnection' => new SmtpConnectionPayload($connection)], 200);
    }
}
