<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use App\Core\BaseModel;

/**
 * Manages school-specific configurable leave types.
 * Replaces hardcoded LeaveRequest::TYPE_* constants.
 *
 * sandwich_rule: NULL = inherit global, 1 = calendar days, 0 = working days only
 */
class LeaveTypeModel extends BaseModel
{
    protected $table          = 'leave_types';
    protected $primaryKey     = 'leave_type_id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'code',
        'name',
        'description',
        'max_days_per_year',
        'is_paid',
        'balance_check',
        'sandwich_rule',
        'color_hex',
        'sort_order',
        'is_active',
    ];

    /**
     * Get all active leave types ordered by sort_order.
     * @return list<array>
     */
    public function findActive(): array
    {
        return $this->where('is_active', 1)
                    ->orderBy('sort_order', 'ASC')
                    ->orderBy('code', 'ASC')
                    ->findAll();
    }

    /**
     * Find a leave type by its code (case-insensitive).
     */
    public function findByCode(string $code): ?array
    {
        $row = $this->where('code', strtoupper($code))->first();
        return $row ?: null;
    }

    /**
     * Returns an array of valid active leave type codes.
     * @return list<string>
     */
    public function getActiveCodes(): array
    {
        return array_column($this->findActive(), 'code');
    }
}
