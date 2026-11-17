<?php

declare(strict_types=1);

namespace Pidgey\Http;

/**
 * Field rules of the API token create body (see
 * `api/endpoints/pidgey-admin/api-tokens.md`).
 *
 * Every failed rule is reported at once, name errors first.
 */
final class ApiTokenRules
{
    public const NAME = 'name';

    public const SCOPES = 'scopes';

    public const SCOPES_EMPTY = 'scopes should not be empty';

    public const SCOPES_NOT_ARRAY = 'scopes must be an array';

    public const SCOPES_UNKNOWN = 'each value in scopes must be one of the following values: send';

    private const KNOWN_SCOPES = ['send'];

    private const MAX_CHARACTERS = 255;

    /**
     * Validates a create body. Extra keys are ignored.
     *
     * @param array<string, mixed> $body Decoded request body.
     *
     * @return array{name: string, scopes: list<string>}
     *
     * @throws ValidationFailedException When the name or the scopes fail a rule.
     */
    public static function forCreate(array $body): array
    {
        $name = (new FieldRules(self::NAME))->trim()->notEmpty()->maxLength(self::MAX_CHARACTERS);
        $scopes = $body[self::SCOPES] ?? null;
        $errors = [...$name->errors($body), ...self::scopeErrors($scopes)];

        if ($errors !== [] || !is_array($scopes)) {
            throw new ValidationFailedException($errors);
        }

        /** @var list<string> $scopes */
        return ['name' => $name->value($body), 'scopes' => array_values(array_unique($scopes))];
    }

    /**
     * Returns the messages of the scopes value.
     *
     * @param mixed $scopes The `scopes` value of the body, null when missing.
     *
     * @return list<string>
     */
    private static function scopeErrors(mixed $scopes): array
    {
        if (!is_array($scopes) || !array_is_list($scopes)) {
            return [self::SCOPES_NOT_ARRAY];
        }

        if ($scopes === []) {
            return [self::SCOPES_EMPTY];
        }

        foreach ($scopes as $scope) {
            if (!is_string($scope) || !in_array($scope, self::KNOWN_SCOPES, true)) {
                return [self::SCOPES_UNKNOWN];
            }
        }

        return [];
    }
}
