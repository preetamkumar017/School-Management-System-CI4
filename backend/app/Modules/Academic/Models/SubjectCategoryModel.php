<?php

declare(strict_types=1);

namespace App\Modules\Academic\Models;

use App\Core\BaseModel;
use App\Modules\Academic\Entities\SubjectCategory;

class SubjectCategoryModel extends BaseModel
{
    protected $table          = 'subject_categories';
    protected $primaryKey     = 'subject_category_id';
    protected $returnType     = SubjectCategory::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'category_name',
        'category_code',
        'description',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function findByCode(string $code): ?SubjectCategory
    {
        return $this->where('category_code', $code)->first();
    }

    public function existsByCode(string $code): bool
    {
        return $this->where('category_code', $code)->countAllResults() > 0;
    }

    public function existsByCodeExceptId(string $code, int $id): bool
    {
        return $this->where('category_code', $code)->where('subject_category_id !=', $id)->countAllResults() > 0;
    }
}
