<?php

declare(strict_types=1);

namespace Nomokonov\SberSdk\Validation;

/**
 * A single field validation failure.
 */
final readonly class ValidationError
{
    public function __construct(
        public string $field,
        public string $message,
    ) {
    }

    /**
     * @return array{field: string, message: string}
     */
    public function toArray(): array
    {
        return ['field' => $this->field, 'message' => $this->message];
    }
}
