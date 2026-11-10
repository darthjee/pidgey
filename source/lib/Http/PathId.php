<?php

declare(strict_types=1);

namespace Pidgey\Http;

use LogicException;

/**
 * Parses the `:id` path parameter of the admin routes (see
 * `api/endpoints/pidgey-admin.md#id-path-parameter`).
 *
 * Runs after the gate and before body validation; the organization-scoped lookup stays in the
 * handler.
 */
final class PathId
{
    public const MALFORMED = 'Validation failed (numeric string is expected)';

    private const FORMAT = '/^[1-9][0-9]*$/D';

    /**
     * Returns the `:id` of the matched route as an integer.
     *
     * @param ProcessingRequest $request  The request being dispatched.
     * @param string            $notFound Message of the resource's `404`, e.g.
     *                                    `SMTP connection not found`.
     *
     * @return integer
     *
     * @throws BadRequestException When the id does not match `^[1-9][0-9]*$`.
     * @throws NotFoundException   When the id is above PHP_INT_MAX (out of range for the column).
     * @throws LogicException      When the matched route has no `:id` param.
     */
    public static function parse(ProcessingRequest $request, string $notFound): int
    {
        $raw = $request->param('id') ?? throw new LogicException('The matched route has no :id param.');

        if (preg_match(self::FORMAT, $raw) !== 1) {
            throw new BadRequestException(self::MALFORMED);
        }

        $id = filter_var($raw, FILTER_VALIDATE_INT);

        if ($id === false) {
            throw new NotFoundException($notFound);
        }

        return $id;
    }
}
