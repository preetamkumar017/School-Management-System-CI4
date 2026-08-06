<?php

declare(strict_types=1);

namespace App\Modules\Academic\DTOs;

use App\Modules\Academic\Entities\Subject;

/**
 * docs/design/academic/Phase-3-DTO-Design.md
 */
final class SubjectResponse
{
    public readonly int $subjectId;
    public readonly string $subjectName;
    public readonly string $subjectCode;

    public function __construct(Subject $subject)
    {
        $this->subjectId   = $subject->subject_id;
        $this->subjectName = $subject->subject_name;
        $this->subjectCode = $subject->subject_code;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'subject_id'   => $this->subjectId,
            'subject_name' => $this->subjectName,
            'subject_code' => $this->subjectCode,
        ];
    }
}
