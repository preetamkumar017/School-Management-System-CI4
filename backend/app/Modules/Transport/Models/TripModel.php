<?php

declare(strict_types=1);

namespace App\Modules\Transport\Models;

use App\Core\BaseModel;
use App\Modules\Transport\Entities\Trip;

/**
 * docs/design/transport/Phase-4-Driver-Trip-Design.md
 */
class TripModel extends BaseModel
{
    protected $table          = 'trips';
    protected $primaryKey     = 'trip_id';
    protected $returnType     = Trip::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'route_id',
        'driver_id',
        'vehicle_id',
        'started_at',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * @return list<Trip>
     */
    public function findByRouteId(int $routeId): array
    {
        return $this->where('route_id', $routeId)->findAll();
    }
}
