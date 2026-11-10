<?php

declare(strict_types=1);

namespace Pidgey\Http\Handler;

use LogicException;
use Pidgey\Auth\AuthenticatedUser;
use Pidgey\Container;
use Pidgey\Crypto\PasswordCipher;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\OperatorLog;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;
use Pidgey\Http\SmtpConnectionPayload;
use Pidgey\Http\SmtpConnectionRules;
use Pidgey\Http\ValidationFailedException;
use Pidgey\Infra\Transaction;
use Pidgey\Model\SmtpConnection;
use Pidgey\Repository\SmtpConnectionRepository;
use Throwable;

/**
 * `GET /smtp-connections.json` (list) and `POST /smtp-connections.json` (create), gated (see
 * `api/endpoints/pidgey-admin/smtp-connections.md`).
 *
 * The organization is always the gate's. A created connection always starts inactive; its
 * password is encrypted with the new row's id as associated data, in the create's transaction.
 * The password is never returned nor logged.
 */
final class SmtpConnectionsHandler extends RequestHandler
{
    /**
     * Builds the handler.
     *
     * @param SmtpConnectionRepository $connections The `smtp_connections` repository.
     * @param Transaction              $transaction Runs the create atomically.
     * @param PasswordCipher           $cipher      Encrypts the SMTP password.
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
     * Lists the organization's connections, or creates one.
     *
     * @param ProcessingRequest $request The request being dispatched, with its AuthenticatedUser.
     *
     * @return Response
     *
     * @throws ValidationFailedException When the create body is invalid (every error at once).
     * @throws LogicException            When the route's method is neither GET nor POST.
     * @throws Throwable                 When the create fails (rolled back, answered `500`).
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
     * Answers `200` with every connection of the organization, in id order.
     *
     * @param AuthenticatedUser $user The caller.
     *
     * @return Response
     */
    private function list(AuthenticatedUser $user): Response
    {
        $items = array_map(
            static fn (SmtpConnection $connection): SmtpConnectionPayload => new SmtpConnectionPayload($connection),
            $this->connections->listByOrganization($user->organizationId()),
        );

        return new JsonResponse(['smtpConnections' => $items], 200);
    }

    /**
     * Validates the body and creates the connection, inactive; answers `201` with the item.
     *
     * Ordering, in one transaction: insert with no password, encrypt with the new id, store the
     * ciphertext, reload. Any failure rolls the whole create back.
     *
     * @param ProcessingRequest $request The request being dispatched.
     * @param AuthenticatedUser $user    The caller.
     *
     * @return Response
     *
     * @throws ValidationFailedException When the body is invalid.
     * @throws Throwable                 When a step of the create fails.
     */
    private function create(ProcessingRequest $request, AuthenticatedUser $user): Response
    {
        $fields = SmtpConnectionRules::forCreate($request->request()->json());
        $organizationId = $user->organizationId();

        $connection = $this->transaction->run(function () use ($fields, $organizationId): SmtpConnection {
            $created = $this->connections->create(
                $organizationId,
                $fields['name'],
                $fields['host'],
                $fields['port'],
                $fields['encryption'],
                $fields['username'],
                null,
                $fields['fromAddress'],
            );

            if ($fields['password'] === null) {
                return $created;
            }

            $this->connections->replaceEncryptedPassword(
                $organizationId,
                $created->id,
                $this->cipher->encrypt($fields['password'], $organizationId, $created->id),
            );

            return $this->connections->findInOrganization($organizationId, $created->id)
                ?? throw new LogicException("SMTP connection {$created->id} vanished during its create.");
        });

        $this->operatorLog->record(
            OperatorLog::SMTP_CONNECTION_CREATE,
            $user->id,
            $organizationId,
            $connection->id,
        );

        return new JsonResponse(['smtpConnection' => new SmtpConnectionPayload($connection)], 201);
    }
}
