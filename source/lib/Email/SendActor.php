<?php

declare(strict_types=1);

namespace Pidgey\Email;

/**
 * Who asked for a send: an API token (`POST /v1/messages`) or a user (the test email).
 */
final readonly class SendActor
{
    /**
     * Builds the actor; use the named constructors.
     *
     * @param string  $field The log context field (`tokenId` or `userId`).
     * @param integer $id    The token or user id.
     */
    private function __construct(private string $field, private int $id)
    {
    }

    /**
     * The acting API token.
     *
     * @param integer $tokenId The token id.
     *
     * @return self
     */
    public static function token(int $tokenId): self
    {
        return new self('tokenId', $tokenId);
    }

    /**
     * The acting user.
     *
     * @param integer $userId The user id.
     *
     * @return self
     */
    public static function user(int $userId): self
    {
        return new self('userId', $userId);
    }

    /**
     * Returns the actor's attempt-log context: `['tokenId' => n]` or `['userId' => n]`.
     *
     * @return array<string, int>
     */
    public function logContext(): array
    {
        return [$this->field => $this->id];
    }
}
