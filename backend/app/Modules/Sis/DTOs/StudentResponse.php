<?php

declare(strict_types=1);

namespace App\Modules\Sis\DTOs;

use App\Modules\Sis\Entities\Student;

/**
 * docs/design/sis/Phase-4.4-DTO-Design.md
 */
final class StudentResponse
{
    public readonly int $studentId;
    public readonly string $admissionNumber;
    public readonly string $fullName;
    public readonly string $dob;
    public readonly ?string $aadhaarNumber;
    public readonly ?int $sectionId;
    public readonly int $applicationId;
    public readonly string $category;
    public readonly string $status;
    public readonly ?string $medicalInfo;
    public readonly ?int $photoDocumentId;

    public function __construct(Student $student)
    {
        $this->studentId       = $student->student_id;
        $this->admissionNumber = $student->admission_number;
        $this->fullName        = $student->full_name;
        $this->dob             = (string) $student->dob;
        $this->aadhaarNumber   = $student->aadhaar_number;
        $this->sectionId       = $student->section_id;
        $this->applicationId   = $student->application_id;
        $this->category        = $student->category;
        $this->status          = $student->status;
        $this->medicalInfo     = $student->medical_info;
        $this->photoDocumentId = $student->photo_document_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'student_id'       => $this->studentId,
            'admission_number' => $this->admissionNumber,
            'full_name'        => $this->fullName,
            'dob'              => $this->dob,
            'aadhaar_number'   => $this->aadhaarNumber,
            'section_id'       => $this->sectionId,
            'application_id'   => $this->applicationId,
            'category'         => $this->category,
            'status'           => $this->status,
            'medical_info'     => $this->medicalInfo,
            'photo_document_id' => $this->photoDocumentId,
        ];
    }
}
