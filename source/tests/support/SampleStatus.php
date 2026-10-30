<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

/**
 * A string-backed enum for tests of enum conversions.
 */
enum SampleStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
}
