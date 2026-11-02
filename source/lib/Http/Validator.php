<?php

declare(strict_types=1);

namespace Pidgey\Http;

use LogicException;

/**
 * Validates a decoded request body against per-field rules, collecting every error at once.
 *
 * Handlers call it inside process(), after the guards. Unknown fields are dropped from the
 * validated output.
 */
final class Validator
{
    /** @var array<string, FieldRules> Declared fields, in declaration order. */
    private array $fields = [];

    /**
     * Builds the validator.
     *
     * @param array<string, mixed> $body Decoded request body, e.g. Request::json().
     */
    public function __construct(private readonly array $body)
    {
    }

    /**
     * Declares a string field and returns its fluent rules.
     *
     * @param string $field Body property name.
     *
     * @return FieldRules
     *
     * @throws LogicException When the field was already declared.
     */
    public function string(string $field): FieldRules
    {
        if (array_key_exists($field, $this->fields)) {
            throw new LogicException('Field already declared: ' . $field);
        }

        return $this->fields[$field] = new FieldRules($field);
    }

    /**
     * Evaluates every declared field and returns only the declared fields, trimmed when declared so.
     *
     * @return array<string, string>
     *
     * @throws ValidationFailedException With every message, fields then rules in declaration order.
     */
    public function validated(): array
    {
        $errors = [];

        foreach ($this->fields as $rules) {
            array_push($errors, ...$rules->errors($this->body));
        }

        if ($errors !== []) {
            throw new ValidationFailedException($errors);
        }

        $values = [];

        foreach ($this->fields as $field => $rules) {
            $values[$field] = $rules->value($this->body);
        }

        return $values;
    }
}
