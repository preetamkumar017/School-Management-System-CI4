<?php

declare(strict_types=1);

namespace App\Modules\Admission\DTOs;

/**
 * docs/design/admission/Phase-3-DTO-Design.md — bare status transition
 * (any pre-ADMITTED status -> REJECTED), no body fields beyond the
 * implicit path {id}.
 */
final class ApplicationRejectRequest
{
}
