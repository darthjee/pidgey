<?php

declare(strict_types=1);

namespace Pidgey\Auth;

/**
 * Result of revoking an API token (see ApiTokens::revoke()).
 */
enum ApiTokenRevokeOutcome
{
    /** This call revoked the token. */
    case Revoked;

    /** The token was already revoked; nothing changed. */
    case AlreadyRevoked;

    /** No token of the organization has this id. */
    case NotFound;
}
