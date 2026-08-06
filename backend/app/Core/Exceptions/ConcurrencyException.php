<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

/**
 * An optimistic-lock version mismatch, or a pessimistic-lock contention
 * that the caller must retry (e.g. SeatAllocation's counters).
 */
class ConcurrencyException extends ApiException
{
    public function __construct(string $errorCode, string $message)
    {
        parent::__construct($errorCode, $message, 409);
    }

    public function category(): string
    {
        return 'concurrency';
    }
}
