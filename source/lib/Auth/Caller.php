<?php

declare(strict_types=1);

namespace Pidgey\Auth;

/**
 * The authenticated party of a request: the gate's user or the Bearer API token.
 *
 * At most one Caller is attached per request; organization-scoped handlers read it with
 * `$request->require(Caller::class)`.
 */
interface Caller
{
    /**
     * Returns the organization the caller acts in.
     *
     * @return integer
     */
    public function organizationId(): int;
}
