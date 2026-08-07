<?php

declare(strict_types=1);

namespace App\Modules\Transport\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/transport/Phase-4-Driver-Trip-Design.md — ENT-TRN-004.
 *
 * @property int|null    $driver_id
 * @property string      $full_name
 * @property string      $license_number
 * @property string|null $license_valid_until
 * @property string      $status
 */
class Driver extends BaseEntity
{
    public const STATUS_ACTIVE   = 'Active';
    public const STATUS_INACTIVE = 'Inactive';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'driver_id' => 'integer',
        ]);

        parent::__construct($data);
    }
}
