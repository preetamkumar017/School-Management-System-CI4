<?php

declare(strict_types=1);

namespace App\Modules\Sis\Mappers;

use App\Modules\Sis\DTOs\StudentGuardianLinkRequest;
use App\Modules\Sis\DTOs\StudentGuardianLinkResponse;
use App\Modules\Sis\Entities\StudentGuardianLink;

/**
 * docs/design/sis/Phase-4.5-Mapper-Design.md
 */
class StudentGuardianLinkMapper
{
    public function toEntity(StudentGuardianLinkRequest $request): StudentGuardianLink
    {
        return new StudentGuardianLink([
            'student_id'         => $request->studentId,
            'guardian_id'        => $request->guardianId,
            'is_primary_contact' => $request->isPrimaryContact,
        ]);
    }

    public function toResponse(StudentGuardianLink $link): StudentGuardianLinkResponse
    {
        return new StudentGuardianLinkResponse($link);
    }
}
