<?php

declare(strict_types=1);

namespace App\Modules\Admission\DTOs;

/**
 * docs/design/admission/Phase-6-Service-Design-Confirm-Enrollment.md §8 —
 * required outputs: updated Application.status = ADMITTED, and the
 * created Student's student_id.
 */
final class ConfirmEnrollmentResult
{
    public function __construct(
        public readonly ApplicationResponse $application,
        public readonly int $studentId,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [...$this->application->toArray(), 'student_id' => $this->studentId];
    }
}
