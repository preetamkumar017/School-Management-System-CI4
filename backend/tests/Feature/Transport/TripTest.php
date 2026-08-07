<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Core\Exceptions\BusinessRuleException;
use Tests\Support\Transport\TransportTestCase;

/**
 * BR-TRN-006 (ADR-019): a trip cannot be logged as started unless both a
 * valid driver and a currently licensed vehicle are assigned to that
 * route.
 *
 * @internal
 */
final class TripTest extends TransportTestCase
{
    public function testTripStartsSuccessfullyWhenDriverAndVehicleAreBothValid(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $driverId  = $this->createDriverFixture(null, '2099-01-01');
        $vehicleId = $this->createVehicleFixture(null, 50, '2099-01-01');
        $routeId   = $this->createRouteFixture(null, ['Stop A'], 5, $vehicleId, $driverId);

        $response = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/transport/trips/start', [
            'route_id' => $routeId,
        ]);

        $response->assertStatus(201);
        $body = $this->decode($response)['data'];
        $this->assertSame('Started', $body['status']);
        $this->assertSame($driverId, $body['driver_id']);
        $this->assertSame($vehicleId, $body['vehicle_id']);
    }

    /**
     * The driver's license is expired — rejected with a driver-specific
     * error code, distinct from any vehicle-side failure.
     */
    public function testTripStartRejectedWhenDriverLicenseIsExpired(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $driverId  = $this->createDriverFixture(null, '2020-01-01');
        $vehicleId = $this->createVehicleFixture(null, 50, '2099-01-01');
        $routeId   = $this->createRouteFixture(null, ['Stop A'], 5, $vehicleId, $driverId);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/transport/trips/start', [
                'route_id' => $routeId,
            ]),
            BusinessRuleException::class,
            'DRIVER_LICENSE_EXPIRED',
            422,
        );
    }

    /**
     * The vehicle's license is expired — rejected with a vehicle-specific
     * error code, distinct from DRIVER_LICENSE_EXPIRED above, proving the
     * BR's "specific expired/missing credential identified" requirement
     * is real, not collapsed into one generic error.
     */
    public function testTripStartRejectedWhenVehicleLicenseIsExpired(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $driverId  = $this->createDriverFixture(null, '2099-01-01');
        $vehicleId = $this->createVehicleFixture(null, 50, '2020-01-01');
        $routeId   = $this->createRouteFixture(null, ['Stop A'], 5, $vehicleId, $driverId);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/transport/trips/start', [
                'route_id' => $routeId,
            ]),
            BusinessRuleException::class,
            'VEHICLE_LICENSE_EXPIRED',
            422,
        );
    }

    /**
     * No driver assigned to the route at all — rejected before any
     * license check runs.
     */
    public function testTripStartRejectedWhenNoDriverAssignedToRoute(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $vehicleId = $this->createVehicleFixture(null, 50, '2099-01-01');
        $routeId   = $this->createRouteFixture(null, ['Stop A'], 5, $vehicleId, null);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/transport/trips/start', [
                'route_id' => $routeId,
            ]),
            BusinessRuleException::class,
            'DRIVER_NOT_ASSIGNED_TO_ROUTE',
            422,
        );
    }

    public function testTripStartRejectedWhenNoVehicleAssignedToRoute(): void
    {
        $user     = $this->createUser();
        $tokens   = $this->loginAs($user['username']);
        $headers  = $this->authHeaders($tokens['access_token']);
        $driverId = $this->createDriverFixture(null, '2099-01-01');
        $routeId  = $this->createRouteFixture(null, ['Stop A'], 5, null, $driverId);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/transport/trips/start', [
                'route_id' => $routeId,
            ]),
            BusinessRuleException::class,
            'VEHICLE_NOT_ASSIGNED_TO_ROUTE',
            422,
        );
    }

    public function testTripStartRejectedWhenDriverLicenseIsMissing(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $driverId  = $this->createDriverFixture(null, null);
        $vehicleId = $this->createVehicleFixture(null, 50, '2099-01-01');
        $routeId   = $this->createRouteFixture(null, ['Stop A'], 5, $vehicleId, $driverId);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/transport/trips/start', [
                'route_id' => $routeId,
            ]),
            BusinessRuleException::class,
            'DRIVER_LICENSE_MISSING',
            422,
        );
    }

    public function testTripStartRejectedWhenDriverIsInactive(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $driverId  = $this->createDriverFixture(null, '2099-01-01', 'Inactive');
        $vehicleId = $this->createVehicleFixture(null, 50, '2099-01-01');
        $routeId   = $this->createRouteFixture(null, ['Stop A'], 5, $vehicleId, $driverId);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/transport/trips/start', [
                'route_id' => $routeId,
            ]),
            BusinessRuleException::class,
            'DRIVER_INACTIVE',
            422,
        );
    }
}
