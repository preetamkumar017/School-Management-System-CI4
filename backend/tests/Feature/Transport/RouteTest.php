<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Core\Exceptions\BusinessRuleException;
use Tests\Support\Transport\TransportTestCase;

/**
 * @internal
 */
final class RouteTest extends TransportTestCase
{
    /**
     * BR-TRN-001: Route.capacity cannot exceed the assigned Vehicle's capacity.
     */
    public function testRouteCapacityCannotExceedAssignedVehicleCapacity(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $vehicleId = $this->createVehicleFixture(null, 20);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/transport/routes', [
                'route_name' => 'Route ' . uniqid('', true),
                'stops_json' => ['Stop A', 'Stop B'],
                'capacity'   => 30,
                'vehicle_id' => $vehicleId,
            ]),
            BusinessRuleException::class,
            'ROUTE_CAPACITY_EXCEEDS_VEHICLE',
            422,
        );
    }

    public function testRouteCapacityWithinVehicleCapacitySucceeds(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $vehicleId = $this->createVehicleFixture(null, 50);

        $response = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/transport/routes', [
            'route_name' => 'Route ' . uniqid('', true),
            'stops_json' => ['Stop A', 'Stop B'],
            'capacity'   => 45,
            'vehicle_id' => $vehicleId,
        ]);

        $response->assertStatus(201);
        $this->assertSame(45, $this->decode($response)['data']['capacity']);
    }

    /**
     * ADR-019 §3: assigning a driver_id that doesn't exist is rejected at
     * assignment time, not silently accepted.
     */
    public function testRouteRejectsUnknownDriverId(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/transport/routes', [
                'route_name' => 'Route ' . uniqid('', true),
                'stops_json' => ['Stop A', 'Stop B'],
                'capacity'   => 30,
                'driver_id'  => 999999,
            ]),
            BusinessRuleException::class,
            'DRIVER_NOT_FOUND',
            422,
        );
    }

    public function testRouteWithAssignedDriverSucceeds(): void
    {
        $user     = $this->createUser();
        $tokens   = $this->loginAs($user['username']);
        $headers  = $this->authHeaders($tokens['access_token']);
        $driverId = $this->createDriverFixture();

        $response = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/transport/routes', [
            'route_name' => 'Route ' . uniqid('', true),
            'stops_json' => ['Stop A', 'Stop B'],
            'capacity'   => 30,
            'driver_id'  => $driverId,
        ]);

        $response->assertStatus(201);
        $this->assertSame($driverId, $this->decode($response)['data']['driver_id']);
    }
}
