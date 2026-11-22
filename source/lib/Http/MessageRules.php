<?php

declare(strict_types=1);

namespace Pidgey\Http;

use Pidgey\Email\Message;

/**
 * Field rules of the `POST /v1/messages` body (see `email/message-scope.md#validation`).
 *
 * Every failed rule is reported at once, fields in the order `to`, `subject`, `text`, `html`,
 * then the "at least one body" rule. An empty `text` or `html` counts as not sent.
 */
final class MessageRules
{
    public const TO = 'to';

    public const SUBJECT = 'subject';

    public const TEXT = 'text';

    public const HTML = 'html';

    public const BODY_REQUIRED = 'text or html is required';

    private const MAX_CHARACTERS = 255;

    /**
     * Validates a send body and builds the message. Extra keys are ignored.
     *
     * @param array<string, mixed> $body Decoded request body.
     *
     * @return Message
     *
     * @throws ValidationFailedException When a field fails a rule or neither body is given.
     */
    public static function forSend(array $body): Message
    {
        $to = (new FieldRules(self::TO))
            ->trim()
            ->notEmpty()
            ->email()
            ->maxLength(self::MAX_CHARACTERS)
            ->noLineBreaks()
            ->noOtherControlCharacters();
        $subject = (new FieldRules(self::SUBJECT))
            ->notEmpty()
            ->maxLength(self::MAX_CHARACTERS)
            ->noControlCharacters();
        $text = (new FieldRules(self::TEXT))->optional();
        $html = (new FieldRules(self::HTML))->optional();

        $errors = [];

        foreach ([$to, $subject] as $rules) {
            array_push($errors, ...$rules->errors($body));
        }

        $bodyErrors = [...$text->errors($body), ...$html->errors($body)];
        $bodies = [self::bodyOf($text, $body), self::bodyOf($html, $body)];

        if ($bodyErrors === [] && $bodies === [null, null]) {
            $bodyErrors[] = self::BODY_REQUIRED;
        }

        array_push($errors, ...$bodyErrors);

        if ($errors !== []) {
            throw new ValidationFailedException($errors);
        }

        return new Message($to->value($body), $subject->value($body), $bodies[0], $bodies[1]);
    }

    /**
     * Returns an optional body field's value, or null when it was not sent or is empty.
     *
     * Only meaningful when the field's rules returned no message for the same body.
     *
     * @param FieldRules           $rules The body field's rules.
     * @param array<string, mixed> $body  Decoded request body.
     *
     * @return string|null
     */
    private static function bodyOf(FieldRules $rules, array $body): ?string
    {
        if ($rules->isOmitted($body) || $rules->errors($body) !== []) {
            return null;
        }

        $value = $rules->value($body);

        return $value === '' ? null : $value;
    }
}
