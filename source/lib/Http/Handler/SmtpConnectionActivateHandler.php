<?php

declare(strict_types=1);

namespace Pidgey\Http\Handler;

use Pidgey\Http\OperatorLog;

/**
 * `POST /smtp-connections/:id/activate.json` (gated): makes the connection the organization's
 * only active connection; idempotent (see
 * `api/endpoints/pidgey-admin/smtp-connections.md#post-smtp-connectionsidactivatejson`).
 *
 * Every other connection is deactivated before the target is activated, under the organization
 * lock, so the unique index on `active_organization_id` is never hit (a violation is a bug and
 * answers `500`).
 */
final class SmtpConnectionActivateHandler extends SmtpConnectionStateHandler
{
    /**
     * Deactivates every other connection of the organization, then activates the target.
     *
     * @param integer $organizationId The caller's organization.
     * @param integer $id             Connection id.
     *
     * @return void
     */
    protected function apply(int $organizationId, int $id): void
    {
        $this->connections->activate($organizationId, $id);
    }

    /**
     * Returns `smtp-connection-activate`.
     *
     * @return string
     */
    protected function action(): string
    {
        return OperatorLog::SMTP_CONNECTION_ACTIVATE;
    }
}
