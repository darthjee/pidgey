<?php

declare(strict_types=1);

namespace Pidgey\Model;

/**
 * Why a refresh token was revoked (`refresh_tokens.revoked_reason`); each case's value is the
 * database value.
 */
enum RevokedReason: string
{
    case Rotated = 'rotated';
    case Logout = 'logout';
    case PasswordReset = 'password_reset';
    case ReplayDetected = 'replay_detected';
    case AdminDenied = 'admin_denied';
    case AdminEdit = 'admin_edit';
}
