<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use RuntimeException;

/**
 * Base for the five known exception categories (Company Development
 * Standard §10). Anything thrown that is NOT one of this class's
 * subclasses is treated as the sixth category, System/Unhandled, by
 * ApiExceptionHandler — there is no SystemException class, deliberately;
 * "unanticipated" is the whole point of that category.
 */
abstract class ApiException extends RuntimeException
{
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly int $httpStatus,
    ) {
        parent::__construct($message);
    }

    abstract public function category(): string;

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
