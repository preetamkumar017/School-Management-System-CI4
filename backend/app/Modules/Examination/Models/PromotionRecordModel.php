<?php

declare(strict_types=1);

namespace App\Modules\Examination\Models;

use App\Core\BaseModel;
use App\Modules\Examination\Entities\PromotionRecord;

/**
 * docs/design/examination/Phase-2-Model-Design.md
 */
class PromotionRecordModel extends BaseModel
{
    protected $table          = 'promotion_records';
    protected $primaryKey     = 'promotion_record_id';
    protected $returnType     = PromotionRecord::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'student_id',
        'from_session_id',
        'to_session_id',
        'from_class_id',
        'to_class_id',
        'academic_closure_confirmed',
        'fee_closure_confirmed',
        'created_by',
        'updated_by',
    ];

    public function existsByStudentAndFromSession(int $studentId, int $fromSessionId): bool
    {
        return $this->where('student_id', $studentId)->where('from_session_id', $fromSessionId)->countAllResults() > 0;
    }

    /**
     * @return list<PromotionRecord>
     */
    public function findByToSession(int $toSessionId): array
    {
        return $this->where('to_session_id', $toSessionId)->findAll();
    }
}
