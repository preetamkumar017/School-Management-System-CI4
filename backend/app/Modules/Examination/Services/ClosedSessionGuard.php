<?php

declare(strict_types=1);

namespace App\Modules\Examination\Services;

use App\Core\Exceptions\BusinessRuleException;
use Config\Services as AppServices;

/**
 * docs/ADR/ADR-005-examination-module-scope-decisions.md §1 — BR-SIS-004
 * closed-academic-year immutability, shared by every Examination Service
 * that mutates a record scoped to an AcademicSession. Intra-module reuse
 * only (all four Services live in Examination) — not a new architectural
 * layer, just avoiding repeating the same guard four times.
 */
trait ClosedSessionGuard
{
    private function assertSessionMutable(int $academicSessionId, ?string $overrideReason): void
    {
        $session = AppServices::academicSessionService()->getSession($academicSessionId);

        if (! in_array($session->status, ['CLOSED', 'ARCHIVED'], true)) {
            return;
        }

        if ($overrideReason === null || trim($overrideReason) === '') {
            throw new BusinessRuleException(
                'RECORD_LOCKED_BY_CLOSED_SESSION',
                'This record belongs to a closed academic session. Supply override_reason to proceed.',
            );
        }
    }
}
