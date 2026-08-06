<?php

declare(strict_types=1);

namespace App\Modules\Examination\DTOs;

/**
 * docs/ADR/ADR-005-examination-module-scope-decisions.md §7 — no
 * ApprovalRequest workflow exists yet; this is the single logged action
 * that stands in for BR-EXM-003's "re-evaluation workflow."
 */
final class MarksRecordReevaluateRequest
{
    public function __construct(
        public readonly float $marksObtained,
        public readonly string $reason,
    ) {
    }
}
