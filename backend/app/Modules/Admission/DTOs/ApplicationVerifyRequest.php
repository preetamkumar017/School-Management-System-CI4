<?php

declare(strict_types=1);

namespace App\Modules\Admission\DTOs;

/**
 * docs/design/admission/Phase-3-DTO-Design.md — bare status transition
 * (SUBMITTED -> VERIFIED), no body fields beyond the implicit path {id}.
 * Kept as its own type rather than folded into a generic status-change
 * DTO so each endpoint's allowed transition is explicit at the type
 * level.
 */
final class ApplicationVerifyRequest
{
}
