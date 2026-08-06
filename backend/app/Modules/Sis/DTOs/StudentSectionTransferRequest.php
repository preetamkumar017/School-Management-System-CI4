<?php

declare(strict_types=1);

namespace App\Modules\Sis\DTOs;

/**
 * docs/design/sis/Phase-4.4-DTO-Design.md — ADR-004 §4: dual-use, serves
 * both a stub's first section assignment (while DRAFT) and any later
 * transfer (while ACTIVE); BR-SIS-005's capacity check applies either way.
 */
final class StudentSectionTransferRequest
{
    public function __construct(public readonly int $newSectionId)
    {
    }
}
