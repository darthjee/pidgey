<?php

declare(strict_types=1);

namespace Pidgey\Http;

use Pidgey\Model\SmtpConnection;
use Pidgey\Model\SmtpEncryption;
use SensitiveParameter;

/**
 * Field and cross-field rules of the SMTP connection create and update bodies (see
 * `api/endpoints/pidgey-admin/smtp-connections.md#field-rules`).
 *
 * Strings are trimmed, except `password`. The field rules report every message at once; the
 * cross-field rules (credential pairing, credentials require encryption, credential
 * redirection) run on the resulting state, only once the field rules pass, and also report
 * every message at once.
 */
final class SmtpConnectionRules
{
    public const PAIRING = 'username and password must be both set or both empty';

    public const ENCRYPTION_REQUIRED = 'username and password require encryption tls or ssl';

    public const PASSWORD_REQUIRED = 'password must be sent again when host, port or encryption changes';

    public const NAME = 'name';

    public const HOST = 'host';

    public const PORT = 'port';

    public const ENCRYPTION = 'encryption';

    public const USERNAME = 'username';

    public const PASSWORD = 'password';

    public const FROM_ADDRESS = 'fromAddress';

    private const MAX_CHARACTERS = 255;

    private const MIN_PORT = 1;

    private const MAX_PORT = 65535;

    /**
     * Validates a create body.
     *
     * `username` and `password` absent, null or "" are null ("no authentication").
     *
     * @param array<string, mixed> $body Decoded request body.
     *
     * @return array{
     *     name: string,
     *     host: string,
     *     port: integer,
     *     encryption: SmtpEncryption,
     *     username: string|null,
     *     password: string|null,
     *     fromAddress: string
     * }
     *
     * @throws ValidationFailedException When a field or cross-field rule fails.
     */
    public static function forCreate(#[SensitiveParameter] array $body): array
    {
        $fields = self::validate($body, false);
        $username = self::nullIfEmpty($fields[self::USERNAME] ?? null);
        $password = self::nullIfEmpty($fields[self::PASSWORD] ?? null);
        $encryption = SmtpEncryption::from((string) $fields[self::ENCRYPTION]);

        self::throwIfAny(self::credentialErrors($username !== null, $password !== null, $encryption));

        return [
            'name' => (string) $fields[self::NAME],
            'host' => (string) $fields[self::HOST],
            'port' => (int) $fields[self::PORT],
            'encryption' => $encryption,
            'username' => $username,
            'password' => $password,
            'fromAddress' => (string) $fields[self::FROM_ADDRESS],
        ];
    }

    /**
     * Validates an update body against the stored row and merges them.
     *
     * A field absent or null is unchanged; "" clears `username` / `password`. A value identical
     * to the stored one (after trimming, exact and case-sensitive) is not a change; a present,
     * non-empty `password` always is.
     *
     * @param array<string, mixed> $body   Decoded request body.
     * @param SmtpConnection       $stored The row, read under its lock.
     *
     * @return SmtpConnectionChange
     *
     * @throws ValidationFailedException When a field or cross-field rule fails.
     */
    public static function forUpdate(#[SensitiveParameter] array $body, SmtpConnection $stored): SmtpConnectionChange
    {
        $fields = self::validate($body, true);
        $sent = array_key_exists(self::PASSWORD, $fields);
        $password = self::nullIfEmpty($fields[self::PASSWORD] ?? null);
        $merged = self::merge($stored, $fields, $sent && $password === null);
        $changed = self::changedFields($stored, $merged, $fields, $password !== null);
        $hasPassword = $password !== null || $merged->encryptedPassword !== null;
        $errors = self::credentialErrors($merged->username !== null, $hasPassword, $merged->encryption);

        if (
            ($merged->username !== null || $hasPassword)
            && $password === null
            && array_intersect($changed, [self::HOST, self::PORT, self::ENCRYPTION]) !== []
        ) {
            $errors[] = self::PASSWORD_REQUIRED;
        }

        self::throwIfAny($errors);

        return new SmtpConnectionChange($merged, $password, $changed);
    }

    /**
     * Declares the field rules and returns the validated (trimmed) values.
     *
     * @param array<string, mixed> $body     Decoded request body.
     * @param boolean              $optional Whether every field is optional (update).
     *
     * @return array<string, string|integer>
     *
     * @throws ValidationFailedException When a field rule fails.
     */
    private static function validate(#[SensitiveParameter] array $body, bool $optional): array
    {
        $validator = new Validator($body);
        $name = $validator->string(self::NAME);
        $host = $validator->string(self::HOST);
        $port = $validator->integer(self::PORT);
        $encryption = $validator->string(self::ENCRYPTION);

        if ($optional) {
            $name->optional();
            $host->optional();
            $port->optional();
            $encryption->optional();
        }

        $name->trim()->notEmpty()->maxLength(self::MAX_CHARACTERS);
        $host->trim()->notEmpty()->maxLength(self::MAX_CHARACTERS)->hostname();
        $port->range(self::MIN_PORT, self::MAX_PORT);
        $encryption->trim()->notEmpty()->oneOf(...array_column(SmtpEncryption::cases(), 'value'));
        $validator->string(self::USERNAME)
            ->optional()
            ->trim()
            ->maxLength(self::MAX_CHARACTERS)
            ->noControlCharacters();
        $validator->string(self::PASSWORD)
            ->optional()
            ->maxLength(self::MAX_CHARACTERS)
            ->noLineBreaks()
            ->noNulByte();
        $fromAddress = $validator->string(self::FROM_ADDRESS);

        if ($optional) {
            $fromAddress->optional();
        }

        $fromAddress->trim()->notEmpty()->email()->maxLength(self::MAX_CHARACTERS)->noLineBreaks();

        return $validator->validated();
    }

    /**
     * Applies the validated fields to the stored row.
     *
     * @param SmtpConnection                $stored        The stored row.
     * @param array<string, string|integer> $fields        Validated fields (present ones only).
     * @param boolean                       $clearPassword Whether the request clears the password.
     *
     * @return SmtpConnection
     */
    private static function merge(SmtpConnection $stored, array $fields, bool $clearPassword): SmtpConnection
    {
        $username = array_key_exists(self::USERNAME, $fields)
            ? self::nullIfEmpty((string) $fields[self::USERNAME])
            : $stored->username;
        $encryption = array_key_exists(self::ENCRYPTION, $fields)
            ? SmtpEncryption::from((string) $fields[self::ENCRYPTION])
            : $stored->encryption;

        return new SmtpConnection(
            $stored->id,
            $stored->organizationId,
            (string) ($fields[self::NAME] ?? $stored->name),
            (string) ($fields[self::HOST] ?? $stored->host),
            (int) ($fields[self::PORT] ?? $stored->port),
            $encryption,
            $username,
            $clearPassword ? null : $stored->encryptedPassword,
            (string) ($fields[self::FROM_ADDRESS] ?? $stored->fromAddress),
            $stored->active,
            $stored->createdAt,
            $stored->updatedAt,
        );
    }

    /**
     * Lists the API names of the fields whose merged value differs from the stored one.
     *
     * @param SmtpConnection                $stored      The stored row.
     * @param SmtpConnection                $merged      The merged row.
     * @param array<string, string|integer> $fields      Validated fields (present ones only).
     * @param boolean                       $newPassword Whether a non-empty password was sent.
     *
     * @return list<string>
     */
    private static function changedFields(
        SmtpConnection $stored,
        SmtpConnection $merged,
        array $fields,
        bool $newPassword,
    ): array {
        $differs = [
            self::NAME => $merged->name !== $stored->name,
            self::HOST => $merged->host !== $stored->host,
            self::PORT => $merged->port !== $stored->port,
            self::ENCRYPTION => $merged->encryption !== $stored->encryption,
            self::USERNAME => $merged->username !== $stored->username,
            self::PASSWORD => $newPassword || $merged->encryptedPassword !== $stored->encryptedPassword,
            self::FROM_ADDRESS => $merged->fromAddress !== $stored->fromAddress,
        ];

        return array_keys(array_filter(
            $differs,
            static fn (bool $changed, string $field): bool => $changed && array_key_exists($field, $fields),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /**
     * Checks the credential pairing and the credentials-require-encryption rules.
     *
     * @param boolean        $hasUsername Whether the resulting state has a username.
     * @param boolean        $hasPassword Whether the resulting state has a password.
     * @param SmtpEncryption $encryption  The resulting encryption.
     *
     * @return list<string>
     */
    private static function credentialErrors(bool $hasUsername, bool $hasPassword, SmtpEncryption $encryption): array
    {
        $errors = [];

        if ($hasUsername !== $hasPassword) {
            $errors[] = self::PAIRING;
        }

        if (($hasUsername || $hasPassword) && $encryption === SmtpEncryption::None) {
            $errors[] = self::ENCRYPTION_REQUIRED;
        }

        return $errors;
    }

    /**
     * Throws the validation failure when there is any message.
     *
     * @param string[] $errors Cross-field messages.
     *
     * @return void
     *
     * @throws ValidationFailedException When the list is not empty.
     */
    private static function throwIfAny(array $errors): void
    {
        if ($errors !== []) {
            throw new ValidationFailedException($errors);
        }
    }

    /**
     * Maps "" to null ("no value").
     *
     * @param string|integer|null $value Validated value.
     *
     * @return string|null
     */
    private static function nullIfEmpty(#[SensitiveParameter] string|int|null $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
