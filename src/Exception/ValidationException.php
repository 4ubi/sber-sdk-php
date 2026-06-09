<?php

declare(strict_types=1);

namespace Nomokonov\SberSdk\Exception;

use Nomokonov\SberSdk\Validation\ValidationError;

/**
 * Thrown when a request payload fails schema validation.
 *
 * Mirrors the behaviour of the Node.js SDK which aggregates all failures
 * and throws a single error containing the list of field/message pairs.
 */
final class ValidationException extends SberApiException
{
    /**
     * @param list<ValidationError> $errors
     */
    public function __construct(private readonly array $errors)
    {
        $payload = array_map(static fn (ValidationError $error): array => $error->toArray(), $errors);

        parent::__construct(
            'Validation failed:' . PHP_EOL . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * @return list<ValidationError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
