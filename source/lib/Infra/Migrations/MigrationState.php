<?php

declare(strict_types=1);

namespace Pidgey\Infra\Migrations;

/**
 * State of a migration as reported by the migrations status.
 */
enum MigrationState: string
{
    case Applied = 'applied';
    case Pending = 'pending';
    case MissingFile = 'missing_file';
    case ChecksumMismatch = 'checksum_mismatch';
    case OutOfOrder = 'out_of_order';
}
