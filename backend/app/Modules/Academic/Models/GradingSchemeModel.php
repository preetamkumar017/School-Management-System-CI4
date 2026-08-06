<?php

declare(strict_types=1);

namespace App\Modules\Academic\Models;

use App\Core\BaseModel;
use App\Modules\Academic\Entities\GradingScheme;

/**
 * docs/design/academic/Phase-2-Model-Design.md
 */
class GradingSchemeModel extends BaseModel
{
    protected $table          = 'grading_schemes';
    protected $primaryKey     = 'grading_scheme_id';
    protected $returnType     = GradingScheme::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'scheme_name',
        'board_type',
        'grade_band_json',
        'created_by',
        'updated_by',
    ];

    protected $beforeInsert = ['stampCreatedBy', 'encodeGradeBandJson'];
    protected $beforeUpdate = ['stampUpdatedBy', 'encodeGradeBandJson'];

    /**
     * Same reasoning as RoleModel::encodePermissionSet — the Query Builder
     * binds a raw PHP array as a tuple, not JSON, unless encoded first.
     */
    protected function encodeGradeBandJson(array $eventData): array
    {
        if (isset($eventData['data']['grade_band_json']) && is_array($eventData['data']['grade_band_json'])) {
            $eventData['data']['grade_band_json'] = json_encode($eventData['data']['grade_band_json']);
        }

        return $eventData;
    }

    public function findBySchemeName(string $value): ?GradingScheme
    {
        return $this->where('scheme_name', $value)->first();
    }

    public function existsBySchemeName(string $value): bool
    {
        return $this->where('scheme_name', $value)->countAllResults() > 0;
    }

    public function existsBySchemeNameExceptId(string $value, int $id): bool
    {
        return $this->where('scheme_name', $value)->where('grading_scheme_id !=', $id)->countAllResults() > 0;
    }

    /**
     * Input to GradingSchemeService::updateGradingScheme's immutability
     * check (Phase 4). Examination is not designed/implemented yet, so
     * there is no `exams` table to join against — this always reports
     * false until Examination (Stage 6) exists, deliberately, rather than
     * guessing at a schema that isn't designed.
     */
    public function isReferencedByClosedExam(int $schemeId): bool
    {
        return false;
    }
}
