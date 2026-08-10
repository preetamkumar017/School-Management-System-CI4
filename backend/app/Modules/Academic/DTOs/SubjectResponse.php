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
    public readonly ?int $subjectCategoryId;
    public readonly int $isLanguageSubject;
    public readonly string $streamApplicability;

    public function __construct(Subject $subject)
    {
        $this->subjectId           = $subject->subject_id;
        $this->subjectName         = $subject->subject_name;
        $this->subjectCode         = $subject->subject_code;
        $this->subjectCategoryId   = $subject->subject_category_id;
        $this->isLanguageSubject   = $subject->is_language_subject ?? 0;
        $this->streamApplicability = $subject->stream_applicability ?? 'ALL';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'subject_id'           => $this->subjectId,
            'subject_name'         => $this->subjectName,
            'subject_code'         => $this->subjectCode,
            'subject_category_id'  => $this->subjectCategoryId,
            'is_language_subject'  => $this->isLanguageSubject,
            'stream_applicability' => $this->streamApplicability,
        ];
    }
}
