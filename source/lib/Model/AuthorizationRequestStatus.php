<?php

declare(strict_types=1);

namespace Pidgey\Model;

/**
 * State of a device authorization request (`authorization_requests.status`); each case's value
 * is the database value.
 */
enum AuthorizationRequestStatus: string
{
    case Open = 'open';
    case Approved = 'approved';
    case Denied = 'denied';
    case Logged = 'logged';
    case Expired = 'expired';
}
