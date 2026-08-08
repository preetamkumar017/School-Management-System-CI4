<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Core\Exceptions\AuthorizationException;
use App\Core\Http\RequestContext;
use App\Modules\Administration\Models\UserModel;
use App\Modules\Transport\Services\RouteService;
use App\Modules\Transport\Services\TransportAllocationService;
use Config\Services;
use Tests\Support\Transport\TransportTestCase;

/**
 * @internal
 * docs/ADR/ADR-024-systemwide-rbac-enforcement.md Phase 2 —
 * `transport.manage` (Tier 1) gates writes; `getAllocation()` allows
 * Tier 2 — a Student may read their own TransportAllocation.
 */
final class TransportRbacTest extends TransportTestCase
{
    protected function tearDown(): void
    {
        RequestContext::reset();
        parent::tearDown();
    }

    public function testCreateRouteRejectedForCallerWithoutManagePermission(): void
    {
        $vehicleId = $this->createVehicleFixture();
        $driverId  = $this->createDriverFixture();
        $user      = $this->createUser($this->createRole(['read']));
        RequestContext::setUserId($user['id']);
        RequestContext::setPermissionSet(['read']);

        $this->assertApiException(
            fn () => Services::routeService()->createRoute(new \App\Modules\Transport\DTOs\CreateRouteRequest(
                'Route ' . uniqid('', true),
                ['Stop A', 'Stop B'],
                40,
                $vehicleId,
                $driverId,
            )),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );
    }

    public function testCreateRouteSucceedsForCallerWithManagePermission(): void
    {
        $vehicleId = $this->createVehicleFixture();
        $driverId  = $this->createDriverFixture();
        $user      = $this->createUser($this->createRole([RouteService::PERMISSION_MANAGE]));
        RequestContext::setUserId($user['id']);
        RequestContext::setPermissionSet([RouteService::PERMISSION_MANAGE]);

        $response = Services::routeService()->createRoute(new \App\Modules\Transport\DTOs\CreateRouteRequest(
            'Route ' . uniqid('', true),
            ['Stop A', 'Stop B'],
            40,
            $vehicleId,
            $driverId,
        ));

        $this->assertNotNull($response->routeId);
    }

    public function testGetAllocationSucceedsForOwningStudent(): void
    {
        $studentId    = $this->createStudentFixture();
        $allocationId = $this->createTransportAllocationFixture($studentId);

        $roleId = $this->createRole(['read']);
        $userId = (new UserModel())->insert([
            'username'      => 'trn_self_' . uniqid('', true),
            'password_hash' => password_hash(self::TEST_PASSWORD, PASSWORD_BCRYPT),
            'role_id'       => $roleId,
            'owner_type'    => 'STUDENT',
            'owner_ref_id'  => $studentId,
            'status'        => 'ACTIVE',
        ], true);
        RequestContext::setUserId($userId);
        RequestContext::setPermissionSet(['read']);

        $response = Services::transportAllocationService()->getAllocation($allocationId);

        $this->assertSame($allocationId, $response->transportAllocationId);
    }

    public function testGetAllocationRejectedForDifferentStudentOwner(): void
    {
        $ownerStudentId = $this->createStudentFixture();
        $otherStudentId = $this->createStudentFixture();
        $allocationId   = $this->createTransportAllocationFixture($ownerStudentId);

        $roleId = $this->createRole(['read']);
        $userId = (new UserModel())->insert([
            'username'      => 'trn_other_' . uniqid('', true),
            'password_hash' => password_hash(self::TEST_PASSWORD, PASSWORD_BCRYPT),
            'role_id'       => $roleId,
            'owner_type'    => 'STUDENT',
            'owner_ref_id'  => $otherStudentId,
            'status'        => 'ACTIVE',
        ], true);
        RequestContext::setUserId($userId);
        RequestContext::setPermissionSet(['read']);

        $this->assertApiException(
            fn () => Services::transportAllocationService()->getAllocation($allocationId),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );
    }
}
