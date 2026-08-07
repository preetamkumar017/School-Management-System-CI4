<?php

declare(strict_types=1);

namespace App\Modules\Transport\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/transport/Phase-4-Driver-Trip-Design.md — ENT-TRN-005. One
 * row per trip-start event (BR-TRN-006); driver_id/vehicle_id are copied
 * off the Route's own assignment at start time, not caller-supplied.
 *
 * @property int|null $trip_id
 * @property int      $route_id
 * @property int      $driver_id
 * @property int      $vehicle_id
 * @property string   $started_at
 * @property string   $status
 */
class Trip extends BaseEntity
{
    public const STATUS_STARTED = 'Started';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'trip_id'    => 'integer',
            'route_id'   => 'integer',
            'driver_id'  => 'integer',
            'vehicle_id' => 'integer',
        ]);

        parent::__construct($data);
    }
}
