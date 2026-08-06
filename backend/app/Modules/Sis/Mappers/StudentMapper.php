<?php

declare(strict_types=1);

namespace App\Modules\Sis\Mappers;

use App\Modules\Sis\DTOs\StudentResponse;
use App\Modules\Sis\DTOs\StudentSectionTransferRequest;
use App\Modules\Sis\DTOs\StudentStatusChangeRequest;
use App\Modules\Sis\DTOs\UpdateStudentRequest;
use App\Modules\Sis\Entities\Student;

/**
 * docs/design/sis/Phase-4.5-Mapper-Design.md
 * Pure data-shape transform — no Model/database access, no business-rule
 * checks (those live in StudentService).
 */
class StudentMapper
{
    /**
     * @param array{application_id: int, admission_number: string, full_name: string, dob: string, category: string, aadhaar_number?: ?string} $data
     */
    public function toEntityFromStub(array $data): Student
    {
        return new Student([
            'application_id'   => $data['application_id'],
            'admission_number' => $data['admission_number'],
            'full_name'        => $data['full_name'],
            'dob'              => $data['dob'],
            'category'         => $data['category'],
            'aadhaar_number'   => $data['aadhaar_number'] ?? null,
            'section_id'       => null,
            'medical_info'     => null,
            'status'           => Student::STATUS_DRAFT,
        ]);
    }

    public function updateEntity(UpdateStudentRequest $request, Student $target): void
    {
        $target->full_name      = $request->fullName;
        $target->dob            = $request->dob;
        $target->category       = $request->category;
        $target->aadhaar_number = $request->aadhaarNumber;
        $target->medical_info   = $request->medicalInfo;
    }

    public function updateSection(StudentSectionTransferRequest $request, Student $target): void
    {
        $target->section_id = $request->newSectionId;
    }

    public function updateStatus(StudentStatusChangeRequest $request, Student $target): void
    {
        $target->status = $request->status;
    }

    public function toResponse(Student $student): StudentResponse
    {
        return new StudentResponse($student);
    }
}
