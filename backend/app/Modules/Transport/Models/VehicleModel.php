<?php

declare(strict_types=1);

namespace App\Modules\Transport\Models;

use App\Core\BaseModel;
use App\Modules\Transport\Entities\Vehicle;

/**
 * docs/design/transport/Phase-2-Model-DTO-Design.md
 */
class VehicleModel extends BaseModel
{
    protected $table          = 'vehicles';
    protected $primaryKey     = 'vehicle_id';
    protected $returnType     = Vehicle::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'registration_no',
        'capacity',
        'gps_device_id',
        'license_valid_until',
        'created_by',
        'updated_by',
    ];

    public function findByRegistrationNo(string $value): ?Vehicle
    {
        return $this->where('registration_no', $value)->first();
    }

    public function existsByRegistrationNo(string $value): bool
    {
        return $this->where('registration_no', $value)->countAllResults() > 0;
    }

    public function existsByRegistrationNoExceptId(string $value, int $id): bool
    {
        return $this->where('registration_no', $value)->where('vehicle_id !=', $id)->countAllResults() > 0;
    }
}
