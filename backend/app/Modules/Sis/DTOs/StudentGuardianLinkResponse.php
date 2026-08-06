<?php

declare(strict_types=1);

namespace App\Modules\Sis\DTOs;

use App\Modules\Sis\Entities\StudentGuardianLink;

/**
 * docs/design/sis/Phase-4.4-DTO-Design.md
 */
final class StudentGuardianLinkResponse
{
    public readonly int $studentId;
    public readonly int $guardianId;
    public readonly bool $isPrimaryContact;

    public function __construct(StudentGuardianLink $link)
    {
        $this->studentId        = $link->student_id;
        $this->guardianId       = $link->guardian_id;
        $this->isPrimaryContact = $link->is_primary_contact;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'student_id'          => $this->studentId,
            'guardian_id'         => $this->guardianId,
            'is_primary_contact'  => $this->isPrimaryContact,
        ];
    }
}
