<?php

declare(strict_types=1);

namespace Pidgey\Infra;

/**
 * Reads `USER_APPROVAL_REQUIRED` (see `architecture/configuration.md`).
 *
 * The exception to the boolean rule: approval is disabled only by the exact value `false`, so a
 * typo, an empty value or no value at all never silently opens registration.
 */
final class UserApproval
{
    public const VARIABLE = 'USER_APPROVAL_REQUIRED';

    private const DISABLED = 'false';

    /**
     * Tells whether new registrations must wait for an admin's approval.
     *
     * @return boolean False only when `USER_APPROVAL_REQUIRED` is exactly `false`.
     */
    public static function required(): bool
    {
        return Env::get(self::VARIABLE) !== self::DISABLED;
    }
}
