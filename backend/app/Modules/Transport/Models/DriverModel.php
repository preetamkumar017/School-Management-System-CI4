<?php

declare(strict_types=1);

namespace App\Modules\Transport\Models;

use App\Core\BaseModel;
use App\Modules\Transport\Entities\Driver;

/**
 * docs/design/transport/Phase-4-Driver-Trip-Design.md
 */
class DriverModel extends BaseModel
{
    protected $table          = 'drivers';
    protected $primaryKey     = 'driver_id';
    protected $returnType     = Driver::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'full_name',
        'license_number',
        'license_valid_until',
        'status',
        'created_by',
        'updated_by',
    ];

    public function existsByLicenseNumber(string $value): bool
    {
        return $this->where('license_number', $value)->countAllResults() > 0;
    }

    public function existsByLicenseNumberExceptId(string $value, int $id): bool
    {
        return $this->where('license_number', $value)->where('driver_id !=', $id)->countAllResults() > 0;
    }
}
