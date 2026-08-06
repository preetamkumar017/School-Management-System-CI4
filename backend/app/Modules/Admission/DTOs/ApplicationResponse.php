<?php

declare(strict_types=1);

namespace App\Modules\Admission\DTOs;

use App\Modules\Admission\Entities\Application;

/**
 * docs/design/admission/Phase-3-DTO-Design.md
 */
final class ApplicationResponse
{
    public readonly int $applicationId;
    public readonly string $applicationReferenceNo;
    public readonly string $applicantName;
    public readonly string $dob;
    public readonly int $classAppliedId;
    public readonly ?string $aadhaarNumber;
    public readonly string $category;
    public readonly string $status;
    public readonly string $submittedAt;
    public readonly ?string $decidedAt;

    public function __construct(Application $application)
    {
        $this->applicationId          = $application->application_id;
        $this->applicationReferenceNo = $application->application_reference_no;
        $this->applicantName          = $application->applicant_name;
        $this->dob                    = (string) $application->dob;
        $this->classAppliedId         = $application->class_applied_id;
        $this->aadhaarNumber          = $application->aadhaar_number;
        $this->category               = $application->category;
        $this->status                 = $application->status;
        $this->submittedAt            = $application->submitted_at->toDateTimeString();
        $this->decidedAt              = $application->decided_at?->toDateTimeString();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'application_id'           => $this->applicationId,
            'application_reference_no' => $this->applicationReferenceNo,
            'applicant_name'           => $this->applicantName,
            'dob'                      => $this->dob,
            'class_applied_id'         => $this->classAppliedId,
            'aadhaar_number'           => $this->aadhaarNumber,
            'category'                 => $this->category,
            'status'                   => $this->status,
            'submitted_at'             => $this->submittedAt,
            'decided_at'               => $this->decidedAt,
        ];
    }
}
