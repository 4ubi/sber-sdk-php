<?php

declare(strict_types=1);

namespace Nomokonov\SberSdk\Validation;

use Nomokonov\SberSdk\Exception\ValidationException;

/**
 * An object schema: a map of field names to {@see Rule}s.
 *
 * Equivalent to a `Joi.object({...})` definition in the Node.js SDK.
 */
final readonly class Schema
{
    /**
     * @param array<string, Rule> $rules
     */
    private function __construct(private array $rules)
    {
    }

    /**
     * @param array<string, Rule> $rules
     */
    public static function make(array $rules): self
    {
        return new self($rules);
    }

    /**
     * Return a new schema extending this one with additional rules,
     * mirroring Joi's `schema.keys({...})`.
     *
     * @param array<string, Rule> $rules
     */
    public function keys(array $rules): self
    {
        return new self([...$this->rules, ...$rules]);
    }

    /**
     * Validate a data array against the schema, returning all errors found.
     *
     * @param array<string, mixed> $data
     * @return list<ValidationError>
     */
    public function validate(array $data, string $prefix = ''): array
    {
        $errors = [];

        foreach ($this->rules as $field => $rule) {
            $path = $prefix === '' ? $field : \sprintf('%s.%s', $prefix, $field);
            $value = $data[$field] ?? null;
            $errors = [...$errors, ...$rule->validate($value, $path)];
        }

        return $errors;
    }

    /**
     * Validate and throw a {@see ValidationException} if any error is found.
     *
     * @param array<string, mixed> $data
     */
    public function assert(array $data): void
    {
        $errors = $this->validate($data);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }
}
