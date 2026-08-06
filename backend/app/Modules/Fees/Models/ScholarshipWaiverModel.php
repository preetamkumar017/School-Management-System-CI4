<?php

declare(strict_types=1);

namespace App\Modules\Fees\Models;

use App\Core\BaseModel;
use App\Modules\Fees\Entities\ScholarshipWaiver;

/**
 * docs/design/fees/Phase-2-Model-DTO-Design.md
 */
class ScholarshipWaiverModel extends BaseModel
{
    protected $table          = 'scholarship_waivers';
    protected $primaryKey     = 'scholarship_waiver_id';
    protected $returnType     = ScholarshipWaiver::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'student_id',
        'fee_head_id',
        'waiver_type',
        'waiver_amount',
        'created_by',
        'updated_by',
    ];

    /**
     * @return list<ScholarshipWaiver>
     */
    public function findByStudentId(int $studentId): array
    {
        return $this->where('student_id', $studentId)->findAll();
    }

    /**
     * The subset relevant to a specific invoice's fee heads, input to
     * generateInvoice's subtraction (ADR-007 §1).
     *
     * @param list<int> $feeHeadIds
     *
     * @return list<ScholarshipWaiver>
     */
    public function findByStudentIdAndFeeHeadIds(int $studentId, array $feeHeadIds): array
    {
        if ($feeHeadIds === []) {
            return [];
        }

        return $this->where('student_id', $studentId)->whereIn('fee_head_id', $feeHeadIds)->findAll();
    }
}
