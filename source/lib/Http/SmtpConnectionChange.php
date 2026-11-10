<?php

declare(strict_types=1);

namespace Pidgey\Http;

use Pidgey\Model\SmtpConnection;
use SensitiveParameter;

/**
 * A validated `PATCH /smtp-connections/:id.json`: the stored row merged with the request, the new
 * plain password (if one was sent) and the API names of the changed fields.
 *
 * The plain password is kept out of var_dump() / print_r() output (see __debugInfo()).
 */
final readonly class SmtpConnectionChange
{
    /**
     * Builds the change.
     *
     * @param SmtpConnection $merged        The stored row with the request applied; its
     *                                      `encryptedPassword` is the stored bytes, or null when
     *                                      the request clears the password.
     * @param string|null    $password      The new plain password, or null when none was sent
     *                                      (absent, null or "").
     * @param string[]       $changedFields API names of the changed fields, in field order
     *                                      (e.g. `host`, `fromAddress`, `password`).
     */
    public function __construct(
        public SmtpConnection $merged,
        #[SensitiveParameter] private ?string $password,
        public array $changedFields,
    ) {
    }

    /**
     * Returns the new plain password, or null when the request sent none.
     *
     * @return string|null
     */
    public function password(): ?string
    {
        return $this->password;
    }

    /**
     * Tells whether at least one field changed.
     *
     * @return boolean
     */
    public function hasChanges(): bool
    {
        return $this->changedFields !== [];
    }

    /**
     * Tells whether the password column must be written (a new password, or a cleared one).
     *
     * @return boolean
     */
    public function passwordChanged(): bool
    {
        return in_array(SmtpConnectionRules::PASSWORD, $this->changedFields, true);
    }

    /**
     * Debug view without the plain password.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'merged' => $this->merged,
            'password' => $this->password === null ? null : '[redacted]',
            'changedFields' => $this->changedFields,
        ];
    }
}
