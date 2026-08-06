<?php

declare(strict_types=1);

namespace App\Modules\Fees\Models;

use App\Core\BaseModel;
use App\Modules\Fees\Entities\FeeStructure;

/**
 * docs/design/fees/Phase-2-Model-DTO-Design.md
 */
class FeeStructureModel extends BaseModel
{
    protected $table          = 'fee_structures';
    protected $primaryKey     = 'fee_structure_id';
    protected $returnType     = FeeStructure::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'class_id',
        'fee_head_id',
        'academic_session_id',
        'category',
        'amount',
        'created_by',
        'updated_by',
    ];

    public function existsByClassHeadSessionCategory(int $classId, int $feeHeadId, int $academicSessionId, string $category): bool
    {
        return $this->where('class_id', $classId)
            ->where('fee_head_id', $feeHeadId)
            ->where('academic_session_id', $academicSessionId)
            ->where('category', $category)
            ->countAllResults() > 0;
    }

    public function existsByClassHeadSessionCategoryExceptId(int $classId, int $feeHeadId, int $academicSessionId, string $category, int $id): bool
    {
        return $this->where('class_id', $classId)
            ->where('fee_head_id', $feeHeadId)
            ->where('academic_session_id', $academicSessionId)
            ->where('category', $category)
            ->where('fee_structure_id !=', $id)
            ->countAllResults() > 0;
    }

    /**
     * @return list<FeeStructure>
     */
    public function findByClassSessionCategory(int $classId, int $academicSessionId, string $category): array
    {
        return $this->where('class_id', $classId)
            ->where('academic_session_id', $academicSessionId)
            ->where('category', $category)
            ->findAll();
    }

    public function findByClassHeadSessionCategory(int $classId, int $feeHeadId, int $academicSessionId, string $category): ?FeeStructure
    {
        return $this->where('class_id', $classId)
            ->where('fee_head_id', $feeHeadId)
            ->where('academic_session_id', $academicSessionId)
            ->where('category', $category)
            ->first();
    }
}
