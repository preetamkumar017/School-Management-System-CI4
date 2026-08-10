<?php

declare(strict_types=1);

namespace App\Modules\Administration\Entities;

use App\Core\BaseEntity;

/**
 * @property int|null    $framework_id
 * @property string      $name
 * @property int         $board_id
 * @property int|null    $grading_scheme_id
 * @property array       $level_divisions
 * @property array|null  $educational_tracks
 * @property array|null  $pass_criteria_json
 * @property array|null  $grace_marks_policy
 * @property array|null  $subject_requirements
 * @property array|null  $language_requirements
 * @property int         $version
 * @property string      $approval_status
 * @property string|null $rejection_reason
 * @property int|null    $approved_by
 * @property string|null $approved_at
 */
class AcademicFramework extends BaseEntity
{
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'framework_id'         => 'integer',
            'board_id'             => 'integer',
            'grading_scheme_id'    => 'integer',
            'level_divisions'      => 'json-array',
            'educational_tracks'   => 'json-array',
            'pass_criteria_json'   => 'json-array',
            'grace_marks_policy'   => 'json-array',
            'subject_requirements' => 'json-array',
            'language_requirements'=> 'json-array',
            'version'              => 'integer',
            'approved_by'          => 'integer',
        ]);
        parent::__construct($data);
    }
}
