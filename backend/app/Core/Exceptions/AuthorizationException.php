<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

/**
 * Covers both "not authenticated" (401) and "authenticated but forbidden"
 * (403) — the Company Development Standard's fixed category list (§10)
 * treats these as one category; $httpStatus still distinguishes them for
 * the HTTP layer.
 */
class AuthorizationException extends ApiException
{
    public function __construct(string $errorCode, string $message, int $httpStatus = 403)
    {
        parent::__construct($errorCode, $message, $httpStatus);
    }

    public function category(): string
    {
        return 'authorization';
    }
}
