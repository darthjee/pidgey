<?php

declare(strict_types=1);

namespace Pidgey\Auth;

/**
 * The caller's username and email after a successful account edit.
 */
final readonly class AccountEditResult
{
    /**
     * Builds the result.
     *
     * @param string $username The username after the update.
     * @param string $email    The email after the update.
     */
    public function __construct(
        public string $username,
        public string $email,
    ) {
    }
}
