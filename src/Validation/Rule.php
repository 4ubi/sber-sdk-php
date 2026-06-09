<?php

declare(strict_types=1);

namespace Nomokonov\SberSdk\Validation;

use DateTimeInterface;

/**
 * A fluent, Joi-inspired validation rule for a single value.
 *
 * The Node.js SDK relies on Joi schemas. This class provides the subset of
 * Joi behaviour the SDK actually uses (types, patterns, length/value bounds,
 * enums, GUID/email checks, nested objects and arrays) with a comparable API.
 */
final class Rule
{
    private bool $required = false;

    private ?string $pattern = null;

    private int|float|null $min = null;

    private int|float|null $max = null;

    private ?int $length = null;

    /** @var list<scalar>|null */
    private ?array $valid = null;

    private bool $guid = false;

    private bool $email = false;

    private bool $integer = false;

    private bool $positive = false;

    private bool $iso = false;

    private ?Rule $items = null;

    private ?Schema $objectSchema = null;

    /**
     * @param 'string'|'number'|'boolean'|'date'|'array'|'object'|'any' $type
     */
    private function __construct(private readonly string $type)
    {
    }

    public static function string(): self
    {
        return new self('string');
    }

    public static function number(): self
    {
        return new self('number');
    }

    public static function boolean(): self
    {
        return new self('boolean');
    }

    public static function date(): self
    {
        return new self('date');
    }

    public static function array(?Rule $items = null): self
    {
        $rule = new self('array');
        $rule->items = $items;

        return $rule;
    }

    public static function object(Schema $schema): self
    {
        $rule = new self('object');
        $rule->objectSchema = $schema;

        return $rule;
    }

    public static function any(): self
    {
        return new self('any');
    }

    public function required(): self
    {
        $this->required = true;

        return $this;
    }

    public function optional(): self
    {
        $this->required = false;

        return $this;
    }

    public function pattern(string $pattern): self
    {
        $this->pattern = $pattern;

        return $this;
    }

    public function min(int|float $min): self
    {
        $this->min = $min;

        return $this;
    }

    public function max(int|float $max): self
    {
        $this->max = $max;

        return $this;
    }

    public function length(int $length): self
    {
        $this->length = $length;

        return $this;
    }

    public function valid(int|float|string|bool ...$values): self
    {
        $this->valid = array_values($values);

        return $this;
    }

    public function guid(): self
    {
        $this->guid = true;

        return $this;
    }

    public function email(): self
    {
        $this->email = true;

        return $this;
    }

    public function integer(): self
    {
        $this->integer = true;

        return $this;
    }

    public function positive(): self
    {
        $this->positive = true;

        return $this;
    }

    public function iso(): self
    {
        $this->iso = true;

        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Validate a value, returning the list of errors found.
     *
     * @return list<ValidationError>
     */
    public function validate(mixed $value, string $path): array
    {
        if ($value === null) {
            if ($this->required) {
                return [new ValidationError($path, \sprintf('"%s" is required', $path))];
            }

            return [];
        }

        return match ($this->type) {
            'string' => $this->validateString($value, $path),
            'number' => $this->validateNumber($value, $path),
            'boolean' => $this->validateBoolean($value, $path),
            'date' => $this->validateDate($value, $path),
            'array' => $this->validateArray($value, $path),
            'object' => $this->validateObject($value, $path),
            default => [],
        };
    }

    /**
     * @return list<ValidationError>
     */
    private function validateString(mixed $value, string $path): array
    {
        if (!\is_string($value)) {
            return [new ValidationError($path, \sprintf('"%s" must be a string', $path))];
        }

        $errors = [];
        $len = mb_strlen($value);

        if ($this->length !== null && $len !== $this->length) {
            $errors[] = new ValidationError($path, \sprintf('"%s" length must be %d characters long', $path, $this->length));
        }
        if ($this->min !== null && $len < $this->min) {
            $errors[] = new ValidationError($path, \sprintf('"%s" length must be at least %d characters long', $path, $this->min));
        }
        if ($this->max !== null && $len > $this->max) {
            $errors[] = new ValidationError($path, \sprintf('"%s" length must be less than or equal to %d characters long', $path, $this->max));
        }
        if ($this->pattern !== null && preg_match($this->pattern, $value) !== 1) {
            $errors[] = new ValidationError($path, \sprintf('"%s" with value "%s" fails to match the required pattern', $path, $value));
        }
        if ($this->guid && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) !== 1) {
            $errors[] = new ValidationError($path, \sprintf('"%s" must be a valid GUID', $path));
        }
        if ($this->email && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $errors[] = new ValidationError($path, \sprintf('"%s" must be a valid email', $path));
        }

        return [...$errors, ...$this->validateEnum($value, $path)];
    }

    /**
     * @return list<ValidationError>
     */
    private function validateNumber(mixed $value, string $path): array
    {
        if (\is_bool($value) || !is_numeric($value)) {
            return [new ValidationError($path, \sprintf('"%s" must be a number', $path))];
        }

        $number = $value + 0;
        $errors = [];

        if ($this->integer && !\is_int($number) && floor($number) !== $number) {
            $errors[] = new ValidationError($path, \sprintf('"%s" must be an integer', $path));
        }
        if ($this->positive && $number <= 0) {
            $errors[] = new ValidationError($path, \sprintf('"%s" must be a positive number', $path));
        }
        if ($this->min !== null && $number < $this->min) {
            $errors[] = new ValidationError($path, \sprintf('"%s" must be greater than or equal to %s', $path, $this->min));
        }
        if ($this->max !== null && $number > $this->max) {
            $errors[] = new ValidationError($path, \sprintf('"%s" must be less than or equal to %s', $path, $this->max));
        }

        return $errors;
    }

    /**
     * @return list<ValidationError>
     */
    private function validateBoolean(mixed $value, string $path): array
    {
        if (!\is_bool($value)) {
            return [new ValidationError($path, \sprintf('"%s" must be a boolean', $path))];
        }

        return [];
    }

    /**
     * @return list<ValidationError>
     */
    private function validateDate(mixed $value, string $path): array
    {
        if (\is_string($value)) {
            if ($this->iso && preg_match('/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})?)?$/', $value) !== 1) {
                return [new ValidationError($path, \sprintf('"%s" must be a valid ISO 8601 date', $path))];
            }

            return strtotime($value) === false
                ? [new ValidationError($path, \sprintf('"%s" must be a valid date', $path))]
                : [];
        }

        if ($value instanceof DateTimeInterface) {
            return [];
        }

        return [new ValidationError($path, \sprintf('"%s" must be a valid date', $path))];
    }

    /**
     * @return list<ValidationError>
     */
    private function validateArray(mixed $value, string $path): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            return [new ValidationError($path, \sprintf('"%s" must be an array', $path))];
        }

        $errors = [];
        $count = \count($value);

        if ($this->min !== null && $count < $this->min) {
            $errors[] = new ValidationError($path, \sprintf('"%s" must contain at least %d items', $path, $this->min));
        }
        if ($this->max !== null && $count > $this->max) {
            $errors[] = new ValidationError($path, \sprintf('"%s" must contain less than or equal to %d items', $path, $this->max));
        }

        if ($this->items instanceof \Nomokonov\SberSdk\Validation\Rule) {
            foreach ($value as $index => $item) {
                $errors = [...$errors, ...$this->items->validate($item, \sprintf('%s[%d]', $path, $index))];
            }
        }

        return $errors;
    }

    /**
     * @return list<ValidationError>
     */
    private function validateObject(mixed $value, string $path): array
    {
        if (!\is_array($value)) {
            return [new ValidationError($path, \sprintf('"%s" must be an object', $path))];
        }

        if (!$this->objectSchema instanceof Schema) {
            return [];
        }

        return $this->objectSchema->validate($value, $path);
    }

    /**
     * @return list<ValidationError>
     */
    private function validateEnum(mixed $value, string $path): array
    {
        if ($this->valid !== null && !\in_array($value, $this->valid, true)) {
            return [new ValidationError(
                $path,
                \sprintf('"%s" must be one of [%s]', $path, implode(', ', array_map(strval(...), $this->valid))),
            )];
        }

        return [];
    }
}
