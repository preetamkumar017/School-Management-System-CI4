<?php

declare(strict_types=1);

namespace App\Modules\Fees\Models;

use App\Core\BaseModel;
use App\Modules\Fees\Entities\FeeHead;

/**
 * docs/design/fees/Phase-2-Model-DTO-Design.md
 */
class FeeHeadModel extends BaseModel
{
    protected $table          = 'fee_heads';
    protected $primaryKey     = 'fee_head_id';
    protected $returnType     = FeeHead::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'fee_head_name',
        'is_taxable',
        'gst_rate',
        'created_by',
        'updated_by',
    ];

    public function findByName(string $value): ?FeeHead
    {
        return $this->where('fee_head_name', $value)->first();
    }

    public function existsByName(string $value): bool
    {
        return $this->where('fee_head_name', $value)->countAllResults() > 0;
    }

    public function existsByNameExceptId(string $value, int $id): bool
    {
        return $this->where('fee_head_name', $value)->where('fee_head_id !=', $id)->countAllResults() > 0;
    }
}
