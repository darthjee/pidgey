<?php

declare(strict_types=1);

namespace Pidgey\Http;

/**
 * Fluent rules of one integer field of a request body, declared through Validator::integer().
 *
 * Only a PHP int decoded from a JSON integer is accepted: a float (even without fraction), a
 * numeric string or a boolean gets `<field> must be an integer` and no further rule. A missing
 * or null value gets `<field> should not be empty`, unless the field is optional(), in which
 * case it gets no message and is omitted from the validated values. Messages follow
 * api/conventions/validation.md.
 */
final class IntegerRules
{
    private bool $optional = false;

    private ?int $min = null;

    private ?int $max = null;

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
     * omitted from the validated values.
     *
     * @return self
     */
    public function optional(): self
    {
        $this->optional = true;

        return $this;
    }

    /**
     * Requires a value between the bounds, inclusive.
     *
     * @param integer $min Lowest accepted value.
     * @param integer $max Highest accepted value.
     *
     * @return self
     */
    public function range(int $min, int $max): self
    {
        $this->min = $min;
        $this->max = $max;

        return $this;
    }

    /**
     * Evaluates the field against the body and returns its messages.
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

        if (!is_int($value)) {
            return [$this->message('must be an integer')];
        }

        if ($this->min !== null && $this->max !== null && ($value < $this->min || $value > $this->max)) {
            return [$this->message('must be between ' . $this->min . ' and ' . $this->max)];
        }

        return [];
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
     * Returns the field's value from the body.
     *
     * Only meaningful once errors() returned no message for the same body.
     *
     * @param array<string, mixed> $body Decoded request body.
     *
     * @return integer
     */
    public function value(array $body): int
    {
        return (int) $body[$this->field];
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
