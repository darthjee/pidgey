<?php

declare(strict_types=1);

namespace Pidgey\Model;

/**
 * Approval status of a user (`users.status`); each case's value is the database value.
 */
enum UserStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Denied = 'denied';
}
