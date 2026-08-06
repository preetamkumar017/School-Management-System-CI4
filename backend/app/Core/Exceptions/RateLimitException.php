<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

class RateLimitException extends ApiException
{
    public function __construct(string $message = 'Too many requests.')
    {
        parent::__construct('RATE_LIMIT_EXCEEDED', $message, 429);
    }

    public function category(): string
    {
        return 'rate_limit';
    }
}
