<?php

declare(strict_types=1);

namespace App\Modules\Transport\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/transport/Phase-1-Domain-Model.md — ENT-TRN-002.
 *
 * @property int|null $vehicle_id
 * @property string   $registration_no
 * @property int      $capacity
 * @property string|null $gps_device_id
 * @property string|null $license_valid_until
 */
class Vehicle extends BaseEntity
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'vehicle_id' => 'integer',
            'capacity'   => 'integer',
        ]);

        parent::__construct($data);
    }
}
