<?php

declare(strict_types=1);

namespace App\Modules\Administration\Models;

use App\Core\BaseModel;
use App\Modules\Administration\Entities\AcademicFramework;

class AcademicFrameworkModel extends BaseModel
{
    protected $table          = 'academic_frameworks';
    protected $primaryKey     = 'framework_id';
    protected $returnType     = AcademicFramework::class;
    protected $useSoftDeletes  = true;

    protected $allowedFields = [
        'name',
        'board_id',
        'grading_scheme_id',
        'level_divisions',
        'educational_tracks',
        'pass_criteria_json',
        'grace_marks_policy',
        'subject_requirements',
        'language_requirements',
        'version',
        'approval_status',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $beforeInsert = ['stampCreatedBy', 'encodeJsonFields'];
    protected $beforeUpdate = ['stampUpdatedBy', 'encodeJsonFields'];

    protected function encodeJsonFields(array $eventData): array
    {
        $fields = [
            'level_divisions',
            'educational_tracks',
            'pass_criteria_json',
            'grace_marks_policy',
            'subject_requirements',
            'language_requirements'
        ];
        foreach ($fields as $field) {
            if (isset($eventData['data'][$field]) && is_array($eventData['data'][$field])) {
                $eventData['data'][$field] = json_encode($eventData['data'][$field]);
            }
        }
        return $eventData;
    }
}
