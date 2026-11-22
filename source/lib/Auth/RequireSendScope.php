<?php

declare(strict_types=1);

namespace Pidgey\Auth;

/**
 * Route middleware: requires the `send` scope (`POST /v1/messages`).
 */
final class RequireSendScope extends RequireScope
{
    protected const SCOPE = 'send';
}
