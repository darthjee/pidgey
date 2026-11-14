<?php

declare(strict_types=1);

namespace Pidgey\Http\Handler;

use LogicException;
use Pidgey\Auth\AuthenticatedUser;
use Pidgey\Container;
use Pidgey\Email\SendActor;
use Pidgey\Email\SendFlow;
use Pidgey\Email\TestEmail;
use Pidgey\Email\TestEmailLimit;
use Pidgey\Http\BadRequestException;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\NotFoundException;
use Pidgey\Http\OperatorLog;
use Pidgey\Http\PathId;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;
use Pidgey\Http\SmtpFailureException;
use Pidgey\Http\TooManyRequestsException;
use Pidgey\Http\Validator;
use Pidgey\Http\ValidationFailedException;
use Pidgey\Repository\SmtpConnectionRepository;
use Pidgey\Repository\UserRepository;
use Throwable;

/**
 * `POST /smtp-connections/:id/test.json`, gated: sends the fixed test email through a
 * connection of the caller's organization (active or not).
 *
 * Order: the `:id`, the optional `to`, the connection lookup (`404`), the limit (`429`), then
 * the send. The recipient is the stored email of an approved user of the organization, never
 * the typed value. The handler never writes to `smtp_connections`; the lease is always
 * released, and the operator line is written only for attempts that reached the send.
 */
final class SmtpConnectionTestHandler extends RequestHandler
{
    private const NOT_AN_ORGANIZATION_USER = 'to must be the email of a user of the organization';

    /**
     * Builds the handler.
     *
     * @param UserRepository           $users       The `users` repository.
     * @param SmtpConnectionRepository $connections The `smtp_connections` repository.
     * @param TestEmailLimit           $limit       The window and the in-flight lease.
     * @param SendFlow                 $sendFlow    The shared send flow.
     * @param OperatorLog              $operatorLog Writes the operator log line.
     */
    public function __construct(
        private readonly UserRepository $users,
        private readonly SmtpConnectionRepository $connections,
        private readonly TestEmailLimit $limit,
        private readonly SendFlow $sendFlow,
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
            $container->userRepository(),
            $container->smtpConnectionRepository(),
            $container->testEmailLimit(),
            $container->sendFlow(),
            $container->operatorLog(),
        );
    }

    /**
     * Sends the test email and answers `201` with the server's reply.
     *
     * @param ProcessingRequest $request The request being dispatched, with its AuthenticatedUser.
     *
     * @return Response
     *
     * @throws BadRequestException       When the `:id` is malformed.
     * @throws ValidationFailedException When `to` is invalid or not a user of the organization.
     * @throws NotFoundException         When no connection of the organization has this id.
     * @throws TooManyRequestsException  When the lease is held or the window is full.
     * @throws SmtpFailureException      When the send failed.
     * @throws LogicException            When the caller's own row cannot be read.
     * @throws Throwable                 When a step fails unexpectedly (answered `500`).
     */
    protected function process(ProcessingRequest $request): Response
    {
        $id = PathId::parse($request, SmtpConnectionHandler::NOT_FOUND);
        $user = $request->require(AuthenticatedUser::class);
        $organizationId = $user->organizationId();
        $recipient = $this->recipient($request, $user);

        $connection = $this->connections->findInOrganization($organizationId, $id)
            ?? throw new NotFoundException(SmtpConnectionHandler::NOT_FOUND);

        $lease = $this->limit->acquire($organizationId, $id);

        try {
            $message = TestEmail::message($recipient);
            $outcome = $this->sendFlow->send($connection, $message, SendActor::user($user->id));
            $this->operatorLog->record(
                OperatorLog::SMTP_CONNECTION_TEST,
                $user->id,
                $organizationId,
                $id,
                null,
                $outcome->failureCode() ?? 'sent',
            );

            if (!$outcome->isSent()) {
                throw SmtpFailureException::fromOutcome($outcome, $connection->port, $connection->encryption);
            }

            $reply = ['code' => $outcome->replyCode(), 'message' => $outcome->replyMessage()];

            return new JsonResponse(['status' => 'sent', 'smtp' => $reply], 201);
        } finally {
            $this->limit->release($lease);
        }
    }

    /**
     * Resolves the recipient: the caller's own stored email, or the stored email of the approved
     * organization user whose email matches the validated `to`.
     *
     * @param ProcessingRequest $request The request being dispatched.
     * @param AuthenticatedUser $user    The caller.
     *
     * @return string The stored email to send to.
     *
     * @throws ValidationFailedException When `to` is invalid or not an organization user's email.
     * @throws LogicException            When the caller's own row cannot be read.
     */
    private function recipient(ProcessingRequest $request, AuthenticatedUser $user): string
    {
        $validator = new Validator($request->request()->json());
        $validator->string('to')->optional()->trim()->notEmpty()->email()->maxLength(255)->noLineBreaks();
        $fields = $validator->validated();

        if (!isset($fields['to'])) {
            return $this->users->findById($user->id)?->email
                ?? throw new LogicException("Authenticated user {$user->id} does not exist.");
        }

        return $this->users->findApprovedEmailInOrganization($user->organizationId(), (string) $fields['to'])
            ?? throw new ValidationFailedException([self::NOT_AN_ORGANIZATION_USER]);
    }
}
