<?php

declare(strict_types=1);

namespace Pidgey\Auth;

/**
 * Why an account edit was refused, returned out of the transaction so that a counted failure is
 * committed before the error is answered (see AccountEdit).
 */
enum AccountEditOutcome
{
    /** The lockout row is locked: `423`, not counted. */
    case Locked;

    /** The caller's row is missing: `401 Invalid session`, not counted. */
    case InvalidSession;

    /** The current password does not match: `400`, counted. */
    case WrongPassword;

    /** The new username belongs to another user: `409 USERNAME_TAKEN`, counted. */
    case UsernameTaken;

    /** The new email belongs to another user: `409 EMAIL_TAKEN`, counted. */
    case EmailTaken;
}
