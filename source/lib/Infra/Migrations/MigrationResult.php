<?php

declare(strict_types=1);

namespace Pidgey\Infra\Migrations;

/**
 * Outcome of a migrations run.
 */
final readonly class MigrationResult
{
    /**
     * Builds the result.
     *
     * @param Migration[] $applied Migrations applied by the run, in order (empty when nothing was pending).
     */
    public function __construct(public array $applied)
    {
    }
}
