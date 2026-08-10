<?php

declare(strict_types=1);

namespace App\Modules\Administration\DTOs;

class SaveAcademicFrameworkRequest
{
    public function __construct(
        public readonly string $name,
        public readonly int $boardId,
        public readonly ?int $gradingSchemeId = null,
        public readonly array $levelDivisions = [],
        public readonly ?array $educationalTracks = null,
        public readonly ?array $passCriteriaJson = null,
        public readonly ?array $graceMarksPolicy = null,
        public readonly ?array $subjectRequirements = null,
        public readonly ?array $languageRequirements = null,
        public readonly ?array $applicableSessionIds = null
    ) {}
}
