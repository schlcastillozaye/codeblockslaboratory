<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Thrown when server-side validation fails inside a service.
 * Carries field-level errors for the UI.
 */
final class ValidationException extends \RuntimeException
{
    /** @var array<string, string> */
    private array $errors;

    /** @param array<string, string> $errors */
    public function __construct(array $errors, string $message = 'Please correct the highlighted fields.')
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
