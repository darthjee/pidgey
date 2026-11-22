<?php

declare(strict_types=1);

namespace Pidgey\Http\Handler;

use JsonException;
use LogicException;
use PDOException;
use Pidgey\Auth\AuthenticatedApiToken;
use Pidgey\Container;
use Pidgey\Email\IdempotencyKeys;
use Pidgey\Email\IdempotencyReplay;
use Pidgey\Email\IdempotencyReservation;
use Pidgey\Email\Message;
use Pidgey\Email\SendActor;
use Pidgey\Email\SendFlow;
use Pidgey\Email\SendOutcome;
use Pidgey\Http\ConflictException;
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\IdempotencyConflictException;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\MessageRules;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;
use Pidgey\Http\SmtpFailureException;
use Pidgey\Http\ValidationFailedException;
use Pidgey\Repository\SmtpConnectionRepository;
use Throwable;

/**
 * `POST /v1/messages`, authenticated by a Bearer API token with the `send` scope: sends one
 * message through the organization's active SMTP connection (see `api/endpoints/sending.md`).
 *
 * Order: the payload, the optional `Idempotency-Key`, the active connection (`409`), then the
 * send. With a key, the answer is stored or released through `IdempotencyKeys::finish()`; the
 * error answers of a reserved key are rendered here, so the stored body is the body sent. Without
 * the header the idempotency storage is never touched.
 */
final class SendMessageHandler extends RequestHandler
{
    public const KEY_HEADER = 'Idempotency-Key';

    public const REPLAYED_HEADER = 'Idempotent-Replayed: true';

    public const NO_SMTP_CONNECTION_CODE = 'NO_SMTP_CONNECTION';

    public const NO_SMTP_CONNECTION_MESSAGE = 'The organization has no active SMTP connection';

    private bool $smtpStarted = false;

    /**
     * Builds the handler.
     *
     * @param SmtpConnectionRepository $connections The `smtp_connections` repository.
     * @param SendFlow                 $sendFlow    The shared send flow.
     * @param IdempotencyKeys          $keys        The `Idempotency-Key` rules.
     * @param ErrorFormatter           $errors      Renders the error answers that get stored.
     */
    public function __construct(
        private readonly SmtpConnectionRepository $connections,
        private readonly SendFlow $sendFlow,
        private readonly IdempotencyKeys $keys,
        private readonly ErrorFormatter $errors,
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
            $container->sendFlow(),
            $container->idempotencyKeys(),
            $container->errorFormatter(),
        );
    }

    /**
     * Sends the message and answers `200` with the server's reply, or replays a stored answer.
     *
     * @param ProcessingRequest $request The request being dispatched, with its AuthenticatedApiToken.
     *
     * @return Response
     *
     * @throws ValidationFailedException    When the payload or the `Idempotency-Key` is invalid.
     * @throws IdempotencyConflictException When the key was used with another payload or is in use.
     * @throws ConflictException            When the organization has no active connection (no key).
     * @throws SmtpFailureException         When the send failed (no key).
     * @throws JsonException                When the payload cannot be hashed.
     * @throws PDOException                 When a statement fails.
     * @throws LogicException               When a stored answer is incomplete.
     * @throws Throwable                    When a step fails unexpectedly (answered `500`).
     */
    protected function process(ProcessingRequest $request): Response
    {
        $token = $request->require(AuthenticatedApiToken::class);
        $message = MessageRules::forSend($request->request()->json());
        $key = $request->request()->header(self::KEY_HEADER);

        if ($key === null) {
            return $this->deliver($token, $message);
        }

        IdempotencyKeys::validate($key);
        $decision = $this->keys->begin($token->organizationId(), $key, $message);

        if ($decision instanceof IdempotencyReplay) {
            return new Response($decision->body, $decision->status, [
                'Content-Type: application/json',
                self::REPLAYED_HEADER,
            ]);
        }

        return $this->deliverReserved($decision, $token, $message);
    }

    /**
     * Sends under a reserved key and stores or releases the answer, whatever it is.
     *
     * @param IdempotencyReservation $reservation The reserved key.
     * @param AuthenticatedApiToken  $token       The caller.
     * @param Message                $message     The validated message.
     *
     * @return Response The exact answer that was stored or released.
     *
     * @throws PDOException When storing or releasing fails.
     */
    private function deliverReserved(
        IdempotencyReservation $reservation,
        AuthenticatedApiToken $token,
        Message $message,
    ): Response {
        try {
            $response = $this->deliver($token, $message);
        } catch (Throwable $error) {
            $response = $this->errors->format($error);
        }

        $this->keys->finish($reservation, $response->status(), $response->body(), $this->smtpStarted);

        return $response;
    }

    /**
     * Sends through the active connection.
     *
     * @param AuthenticatedApiToken $token   The caller.
     * @param Message               $message The validated message.
     *
     * @return Response `200` with the server's reply.
     *
     * @throws ConflictException    When the organization has no active connection.
     * @throws SmtpFailureException When the send failed.
     * @throws Throwable            When a step fails unexpectedly.
     */
    private function deliver(AuthenticatedApiToken $token, Message $message): Response
    {
        $connection = $this->connections->findActiveInOrganization($token->organizationId())
            ?? throw new ConflictException(self::NO_SMTP_CONNECTION_MESSAGE, self::NO_SMTP_CONNECTION_CODE);

        $this->smtpStarted = true;
        $outcome = $this->sendFlow->send($connection, $message, SendActor::token($token->id));
        $this->smtpStarted = $outcome->step() !== SendOutcome::STEP_SEND_TIME_CHECK;

        if (!$outcome->isSent()) {
            throw SmtpFailureException::fromOutcome($outcome, $connection->port, $connection->encryption);
        }

        $reply = ['code' => $outcome->replyCode(), 'message' => $outcome->replyMessage()];

        return new JsonResponse(['status' => 'sent', 'smtp' => $reply]);
    }
}
