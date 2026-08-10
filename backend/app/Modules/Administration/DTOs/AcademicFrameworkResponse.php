<?php

declare(strict_types=1);

namespace App\Modules\Administration\DTOs;

use App\Modules\Administration\Entities\AcademicFramework;

class AcademicFrameworkResponse
{
    public readonly ?int $frameworkId;
    public readonly string $name;
    public readonly int $boardId;
    public readonly ?int $gradingSchemeId;
    public readonly array $levelDivisions;
    public readonly ?array $educationalTracks;
    public readonly ?array $passCriteriaJson;
    public readonly ?array $graceMarksPolicy;
    public readonly ?array $subjectRequirements;
    public readonly ?array $languageRequirements;
    public readonly int $version;
    public readonly string $approvalStatus;
    public readonly ?string $rejectionReason;
    public readonly ?int $approvedBy;
    public readonly ?string $approvedAt;
    public readonly ?string $boardName;
    public readonly ?string $gradingSchemeName;
    public readonly array $applicableSessionIds;

    public function __construct(
        AcademicFramework $fw,
        ?string $boardName = null,
        ?string $gradingSchemeName = null,
        array $applicableSessionIds = []
    ) {
        $this->frameworkId          = $fw->framework_id;
        $this->name                 = $fw->name;
        $this->boardId              = $fw->board_id;
        $this->gradingSchemeId      = $fw->grading_scheme_id;
        $this->levelDivisions       = $fw->level_divisions;
        $this->educationalTracks    = $fw->educational_tracks;
        $this->passCriteriaJson      = $fw->pass_criteria_json;
        $this->graceMarksPolicy     = $fw->grace_marks_policy;
        $this->subjectRequirements  = $fw->subject_requirements;
        $this->languageRequirements = $fw->language_requirements;
        $this->version              = $fw->version;
        $this->approvalStatus       = $fw->approval_status;
        $this->rejectionReason      = $fw->rejection_reason;
        $this->approvedBy           = $fw->approved_by;
        $this->approvedAt           = $fw->approved_at;
        $this->boardName            = $boardName;
        $this->gradingSchemeName    = $gradingSchemeName;
        $this->applicableSessionIds = $applicableSessionIds;
    }

    public function toArray(): array
    {
        return [
            'framework_id'          => $this->frameworkId,
            'name'                  => $this->name,
            'board_id'              => $this->boardId,
            'grading_scheme_id'      => $this->gradingSchemeId,
            'level_divisions'       => $this->levelDivisions,
            'educational_tracks'    => $this->educationalTracks,
            'pass_criteria_json'    => $this->passCriteriaJson,
            'grace_marks_policy'    => $this->graceMarksPolicy,
            'subject_requirements'  => $this->subjectRequirements,
            'language_requirements' => $this->languageRequirements,
            'version'               => $this->version,
            'approval_status'       => $this->approvalStatus,
            'rejection_reason'      => $this->rejectionReason,
            'approved_by'           => $this->approvedBy,
            'approved_at'           => $this->approvedAt,
            'board_name'            => $this->boardName,
            'grading_scheme_name'   => $this->gradingSchemeName,
            'applicable_session_ids'=> $this->applicableSessionIds,
        ];
    }
}
