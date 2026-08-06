<?php

declare(strict_types=1);

namespace App\Modules\Fees\DTOs;

/**
 * docs/ADR/ADR-007-fees-module-scope-decisions.md §8 — reason is always
 * required and logged via AuditLog::ACTION_OVERRIDE, even though the
 * Finance-Team-only role restriction (BR-FEE-002) isn't enforced.
 */
final class VoidRefundRequest
{
    public function __construct(public readonly string $reason)
    {
    }
}
