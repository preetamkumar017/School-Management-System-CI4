<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Exceptions\ValidationException;
use Tests\Support\Transport\TransportTestCase;

/**
 * @internal
 */
final class TransportAllocationTest extends TransportTestCase
{
    public function testAllocateSucceeds(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $routeId   = $this->createRouteFixture(null, ['Stop A', 'Stop B'], 2);
        $studentId = $this->createStudentFixture();

        $response = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/transport/allocations', [
            'student_id'        => $studentId,
            'route_id'          => $routeId,
            'stop_name'         => 'Stop A',
            'emergency_contact' => '9876500000',
        ]);

        $response->assertStatus(201);
        $this->assertSame('Active', $this->decode($response)['data']['status']);
    }

    /**
     * BR-TRN-004: emergency_contact must be 10-digit numeric.
     */
    public function testAllocateRejectsInvalidEmergencyContact(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);
        $routeId = $this->createRouteFixture();

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/transport/allocations', [
                'student_id'        => $this->createStudentFixture(),
                'route_id'          => $routeId,
                'stop_name'         => 'Stop A',
                'emergency_contact' => '12345',
            ]),
            ValidationException::class,
            'VALIDATION_FAILED',
            422,
        );
    }

    /**
     * BR-TRN-002: a student cannot hold two active allocations at once.
     */
    public function testStudentCannotHaveTwoActiveAllocations(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $studentId = $this->createStudentFixture();
        $this->createTransportAllocationFixture($studentId, $this->createRouteFixture(null, ['Stop A'], 5));

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/transport/allocations', [
                'student_id'        => $studentId,
                'route_id'          => $this->createRouteFixture(null, ['Stop B'], 5),
                'stop_name'         => 'Stop B',
                'emergency_contact' => '9876500000',
            ]),
            BusinessRuleException::class,
            'STUDENT_ALREADY_ALLOCATED',
            422,
        );
    }

    /**
     * BR-TRN-001: allocation is blocked once the route reaches capacity —
     * proves the row-lock-guarded ceiling, same shape as Admission's
     * SeatAllocation (ADR-009 §9).
     */
    public function testAllocationBlockedAtRouteCapacity(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);
        $routeId = $this->createRouteFixture(null, ['Stop A'], 1);

        $this->createTransportAllocationFixture($this->createStudentFixture(), $routeId, 'Stop A');

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/transport/allocations', [
                'student_id'        => $this->createStudentFixture(),
                'route_id'          => $routeId,
                'stop_name'         => 'Stop A',
                'emergency_contact' => '9876500000',
            ]),
            BusinessRuleException::class,
            'ROUTE_CAPACITY_EXCEEDED',
            422,
        );
    }

    public function testDeallocateFreesCapacityForNextAllocation(): void
    {
        $user          = $this->createUser();
        $tokens        = $this->loginAs($user['username']);
        $headers       = $this->authHeaders($tokens['access_token']);
        $routeId       = $this->createRouteFixture(null, ['Stop A'], 1);
        $allocationId  = $this->createTransportAllocationFixture($this->createStudentFixture(), $routeId, 'Stop A');

        $this->withHeaders($headers)->post("api/v1/transport/allocations/{$allocationId}/deallocate")->assertStatus(200);

        $response = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/transport/allocations', [
            'student_id'        => $this->createStudentFixture(),
            'route_id'          => $routeId,
            'stop_name'         => 'Stop A',
            'emergency_contact' => '9876500000',
        ]);

        $response->assertStatus(201);
    }
}
