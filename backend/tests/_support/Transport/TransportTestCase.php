<?php

declare(strict_types=1);

namespace Tests\Support\Transport;

use App\Modules\Transport\Models\RouteModel;
use App\Modules\Transport\Models\TransportAllocationModel;
use App\Modules\Transport\Models\VehicleModel;
use Tests\Support\Library\LibraryTestCase;

/**
 * @internal
 */
abstract class TransportTestCase extends LibraryTestCase
{
    protected function createVehicleFixture(?string $registrationNo = null, int $capacity = 50): int
    {
        return (new VehicleModel())->insert([
            'registration_no' => $registrationNo ?? ('VEH-' . random_int(100000, 999999)),
            'capacity'        => $capacity,
        ], true);
    }

    protected function createRouteFixture(
        ?string $routeName = null,
        array $stops = ['Stop A', 'Stop B'],
        int $capacity = 2,
        ?int $vehicleId = null,
    ): int {
        return (new RouteModel())->insert([
            'route_name' => $routeName ?? ('Route ' . uniqid('', true)),
            'stops_json' => $stops,
            'capacity'   => $capacity,
            'vehicle_id' => $vehicleId,
        ], true);
    }

    protected function createTransportAllocationFixture(
        ?int $studentId = null,
        ?int $routeId = null,
        string $stopName = 'Stop A',
        string $status = 'Active',
    ): int {
        return (new TransportAllocationModel())->insert([
            'student_id'        => $studentId ?? $this->createStudentFixture(),
            'route_id'          => $routeId ?? $this->createRouteFixture(),
            'stop_name'         => $stopName,
            'emergency_contact' => '9876500000',
            'status'            => $status,
        ], true);
    }
}
