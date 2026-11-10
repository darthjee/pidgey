<?php

declare(strict_types=1);

namespace Pidgey\Http;

use Closure;

/**
 * Fluent rules of one string field of a request body, declared through Validator::string().
 *
 * A missing, null, wrong-type or (with notEmpty()) empty value gets exactly one message;
 * otherwise every other rule runs, in declaration order, and each failure adds its message.
 * An optional() field that is absent or null gets no message and is omitted from the validated
 * values. Messages follow api/conventions/validation.md.
 */
final class FieldRules
{
    private bool $optional = false;

    private bool $trim = false;

    private bool $notEmpty = false;

    /** @var list<Closure(string): ?string> Rules returning a message suffix on failure, or null. */
    private array $rules = [];

    /**
     * Builds the rules of a field.
     *
     * @param string $field Body property name, used as the message prefix.
     */
    public function __construct(private readonly string $field)
    {
    }

    /**
     * Makes the field optional: an absent or null value is "not present", gets no message and is
     * omitted from the validated values. A present value still runs every rule.
     *
     * @return self
     */
    public function optional(): self
    {
        $this->optional = true;

        return $this;
    }

    /**
     * Trims surrounding whitespace before validation (opt-in; never for passwords or tokens).
     *
     * @return self
     */
    public function trim(): self
    {
        $this->trim = true;

        return $this;
    }

    /**
     * Rejects an empty value, checked after trimming.
     *
     * @return self
     */
    public function notEmpty(): self
    {
        $this->notEmpty = true;

        return $this;
    }

    /**
     * Requires a value accepted by FILTER_VALIDATE_EMAIL.
     *
     * @return self
     */
    public function email(): self
    {
        return $this->rule(
            static fn (string $value): ?string => filter_var($value, FILTER_VALIDATE_EMAIL) === false
                ? 'must be an email'
                : null,
        );
    }

    /**
     * Requires at least the given number of UTF-8 characters.
     *
     * @param integer $min Minimum length, in characters.
     *
     * @return self
     */
    public function minLength(int $min): self
    {
        return $this->rule(
            static fn (string $value): ?string => mb_strlen($value, 'UTF-8') < $min
                ? 'must be longer than or equal to ' . $min . ' characters'
                : null,
        );
    }

    /**
     * Allows at most the given number of UTF-8 characters.
     *
     * @param integer $max Maximum length, in characters.
     *
     * @return self
     */
    public function maxLength(int $max): self
    {
        return $this->rule(
            static fn (string $value): ?string => mb_strlen($value, 'UTF-8') > $max
                ? 'must be shorter than or equal to ' . $max . ' characters'
                : null,
        );
    }

    /**
     * Allows at most the given number of bytes.
     *
     * @param integer $max Maximum length, in bytes.
     *
     * @return self
     */
    public function maxBytes(int $max): self
    {
        return $this->rule(
            static fn (string $value): ?string => strlen($value) > $max
                ? 'must be shorter than or equal to ' . $max . ' bytes'
                : null,
        );
    }

    /**
     * Rejects a value containing a NUL byte.
     *
     * @return self
     */
    public function noNulByte(): self
    {
        return $this->rule(
            static fn (string $value): ?string => str_contains($value, "\0") ? 'must not contain a NUL byte' : null,
        );
    }

    /**
     * Requires one of the given values, compared exactly (case-sensitive).
     *
     * @param string ...$values Accepted values, listed in the message in this order.
     *
     * @return self
     */
    public function oneOf(string ...$values): self
    {
        return $this->rule(
            static fn (string $value): ?string => in_array($value, $values, true)
                ? null
                : 'must be one of the following values: ' . implode(', ', $values),
        );
    }

    /**
     * Requires an ASCII hostname or an IP literal, see HostName::isValid().
     *
     * @return self
     */
    public function hostname(): self
    {
        return $this->rule(
            static fn (string $value): ?string => HostName::isValid($value)
                ? null
                : 'must be a hostname or an IP address',
        );
    }

    /**
     * Rejects a value containing a C0 control character (U+0000–U+001F) or DEL (U+007F).
     *
     * @return self
     */
    public function noControlCharacters(): self
    {
        return $this->rule(
            static fn (string $value): ?string => preg_match('/[\x00-\x1F\x7F]/', $value) === 1
                ? 'must not contain control characters'
                : null,
        );
    }

    /**
     * Rejects a value containing a CR or an LF.
     *
     * @return self
     */
    public function noLineBreaks(): self
    {
        return $this->rule(
            static fn (string $value): ?string => strpbrk($value, "\r\n") !== false
                ? 'must not contain line breaks'
                : null,
        );
    }

    /**
     * Evaluates the field against the body and returns its messages, in declaration order.
     *
     * @param array<string, mixed> $body Decoded request body.
     *
     * @return list<string>
     */
    public function errors(array $body): array
    {
        if ($this->isOmitted($body)) {
            return [];
        }

        $value = $body[$this->field] ?? null;

        if ($value === null) {
            return [$this->message('should not be empty')];
        }

        if (!is_string($value)) {
            return [$this->message('must be a string')];
        }

        $value = $this->normalize($value);

        if ($this->notEmpty && $value === '') {
            return [$this->message('should not be empty')];
        }

        $errors = [];

        foreach ($this->rules as $rule) {
            $failure = $rule($value);

            if ($failure !== null) {
                $errors[] = $this->message($failure);
            }
        }

        return $errors;
    }

    /**
     * Tells whether the field is optional and absent or null in the body, i.e. "not present".
     *
     * @param array<string, mixed> $body Decoded request body.
     *
     * @return boolean
     */
    public function isOmitted(array $body): bool
    {
        return $this->optional && ($body[$this->field] ?? null) === null;
    }

    /**
     * Returns the field's value from the body, trimmed when trim() was declared.
     *
     * Only meaningful once errors() returned no message for the same body.
     *
     * @param array<string, mixed> $body Decoded request body.
     *
     * @return string
     */
    public function value(array $body): string
    {
        return $this->normalize((string) $body[$this->field]);
    }

    /**
     * Appends a rule.
     *
     * @param Closure $rule Takes the value; returns a message suffix on failure, or null.
     *
     * @return self
     */
    private function rule(Closure $rule): self
    {
        $this->rules[] = $rule;

        return $this;
    }

    /**
     * Trims the value when trim() was declared.
     *
     * @param string $value Raw value.
     *
     * @return string
     */
    private function normalize(string $value): string
    {
        return $this->trim ? trim($value) : $value;
    }

    /**
     * Prefixes a message with the field name.
     *
     * @param string $suffix Message without the field name.
     *
     * @return string
     */
    private function message(string $suffix): string
    {
        return $this->field . ' ' . $suffix;
    }
}
