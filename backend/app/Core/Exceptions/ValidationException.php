<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

/**
 * Field/format validation failures — always returns every failing field
 * together, never fail-fast on the first one (Company Development
 * Standard §10).
 */
class ValidationException extends ApiException
{
    /**
     * @param array<string, string> $fields
     */
    public function __construct(private readonly array $fields, string $message = 'The given data was invalid.')
    {
        parent::__construct('VALIDATION_FAILED', $message, 422);
    }

    public function category(): string
    {
        return 'validation';
    }

    /**
     * @return array<string, string>
     */
    public function fields(): array
    {
        return $this->fields;
    }
}
