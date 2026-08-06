<?php

declare(strict_types=1);

namespace App\Modules\Sis\DTOs;

/**
 * docs/design/sis/Phase-4.4-DTO-Design.md — forward-only transition;
 * DRAFT -> ACTIVE gated by BR-SIS-003 and BR-SIS-006 (Service layer).
 */
final class StudentStatusChangeRequest
{
    public function __construct(public readonly string $status)
    {
    }
}
