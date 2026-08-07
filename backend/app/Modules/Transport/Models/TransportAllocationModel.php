<?php

declare(strict_types=1);

namespace App\Modules\Transport\Models;

use App\Core\BaseModel;
use App\Modules\Transport\Entities\TransportAllocation;

/**
 * docs/design/transport/Phase-2-Model-DTO-Design.md
 */
class TransportAllocationModel extends BaseModel
{
    protected $table          = 'transport_allocations';
    protected $primaryKey     = 'transport_allocation_id';
    protected $returnType     = TransportAllocation::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'student_id',
        'route_id',
        'stop_name',
        'emergency_contact',
        'status',
        'created_by',
        'updated_by',
    ];

    public function countActiveByRouteId(int $routeId): int
    {
        return $this->where('route_id', $routeId)
            ->where('status', TransportAllocation::STATUS_ACTIVE)
            ->countAllResults();
    }

    public function existsActiveByStudentId(int $studentId): bool
    {
        return $this->where('student_id', $studentId)
            ->where('status', TransportAllocation::STATUS_ACTIVE)
            ->countAllResults() > 0;
    }

    /**
     * @return list<TransportAllocation>
     */
    public function findByRouteId(int $routeId): array
    {
        return $this->where('route_id', $routeId)->findAll();
    }
}
