<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

/**
 * A named business rule was violated (e.g. GRADING_SCHEME_LOCKED_BY_CLOSED_EXAM,
 * BR-SIS-002 uniqueness, BR-ADM-001 capacity). $errorCode is the stable,
 * caller-visible rule reference — never leak the internal reasoning beyond
 * $message (Company Development Standard §10).
 */
class BusinessRuleException extends ApiException
{
    public function __construct(string $errorCode, string $message)
    {
        parent::__construct($errorCode, $message, 422);
    }

    public function category(): string
    {
        return 'business_rule';
    }
}
