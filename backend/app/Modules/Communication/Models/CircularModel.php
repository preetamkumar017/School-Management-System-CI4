<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use App\Core\BaseModel;
use App\Modules\Communication\Entities\Circular;

/**
 * docs/design/communication/Phase-2-Model-DTO-Design.md
 */
class CircularModel extends BaseModel
{
    protected $table          = 'circulars';
    protected $primaryKey     = 'circular_id';
    protected $returnType     = Circular::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'author_id',
        'post_type',
        'title',
        'body',
        'target_audience',
        'posted_at',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * @return list<Circular>
     */
    public function findByTargetAudience(string $targetAudience): array
    {
        return $this->where('target_audience', $targetAudience)
            ->orderBy('posted_at', 'DESC')
            ->findAll();
    }
}
