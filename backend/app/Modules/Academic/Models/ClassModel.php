<?php

declare(strict_types=1);

namespace App\Modules\Academic\Models;

use App\Core\BaseModel;
use App\Modules\Academic\Entities\AcademicClass;

/**
 * docs/design/academic/Phase-2-Model-Design.md
 */
class ClassModel extends BaseModel
{
    protected $table          = 'classes';
    protected $primaryKey     = 'class_id';
    protected $returnType     = AcademicClass::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'class_name',
        'sequence_order',
        'created_by',
        'updated_by',
    ];

    public function findByClassName(string $value): ?AcademicClass
    {
        return $this->where('class_name', $value)->first();
    }

    public function existsByClassName(string $value): bool
    {
        return $this->where('class_name', $value)->countAllResults() > 0;
    }

    public function existsByClassNameExceptId(string $value, int $id): bool
    {
        return $this->where('class_name', $value)->where('class_id !=', $id)->countAllResults() > 0;
    }

    public function existsBySequenceOrder(int $value): bool
    {
        return $this->where('sequence_order', $value)->countAllResults() > 0;
    }

    public function existsBySequenceOrderExceptId(int $value, int $id): bool
    {
        return $this->where('sequence_order', $value)->where('class_id !=', $id)->countAllResults() > 0;
    }

    /**
     * @return list<AcademicClass>
     */
    public function findAllOrderedBySequence(): array
    {
        return $this->orderBy('sequence_order', 'ASC')->findAll();
    }
}
