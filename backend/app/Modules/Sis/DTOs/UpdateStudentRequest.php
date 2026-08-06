<?php

declare(strict_types=1);

namespace App\Modules\Sis\DTOs;

/**
 * docs/design/sis/Phase-4.4-DTO-Design.md — section_id/application_id
 * excluded: application_id is immutable post-creation, section_id moves
 * through StudentSectionTransferRequest (BR-SIS-005-gated).
 */
final class UpdateStudentRequest
{
    public function __construct(
        public readonly string $fullName,
        public readonly string $dob,
        public readonly string $category,
        public readonly ?string $aadhaarNumber,
        public readonly ?string $medicalInfo,
    ) {
    }
}
