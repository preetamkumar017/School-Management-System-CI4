<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Exceptions\ValidationException;
use App\Modules\Fees\Models\FeeStructureModel;
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

    /**
     * ADR-014 §1 (BR-TRN-005): changing an allocation's route recomputes
     * that student's recalculable (UNPAID, unlocked) invoice total —
     * dropping the old route's tier fee and picking up the new route's.
     */
    public function testChangeRouteRecalculatesUnpaidInvoiceTotal(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $classId   = $this->createClassFixture();
        $sectionId = $this->createSection($classId);
        $sessionId = $this->createAcademicSession();
        $studentId = $this->createStudentFixture(null, $sectionId, 'ACTIVE', 'GENERAL');

        $tuitionHeadId = $this->createFeeHeadFixture('Tuition ' . uniqid('', true));
        $this->createFeeStructureFixture($classId, $tuitionHeadId, $sessionId, 'GENERAL', 5000.0);

        $transportHeadId = $this->createFeeHeadFixture('Transport Tier ' . uniqid('', true));
        $oldRouteId      = $this->createRouteFixture(null, ['Stop A'], 5);
        $newRouteId      = $this->createRouteFixture(null, ['Stop B'], 5);

        (new FeeStructureModel())->insert([
            'class_id' => $classId, 'fee_head_id' => $transportHeadId,
            'academic_session_id' => $sessionId, 'route_id' => $oldRouteId,
            'category' => 'GENERAL', 'amount' => 1500.0,
        ], true);
        (new FeeStructureModel())->insert([
            'class_id' => $classId, 'fee_head_id' => $transportHeadId,
            'academic_session_id' => $sessionId, 'route_id' => $newRouteId,
            'category' => 'GENERAL', 'amount' => 2200.0,
        ], true);

        $allocationId = $this->createTransportAllocationFixture($studentId, $oldRouteId, 'Stop A');

        $invoiceCreate = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/fees/invoices', [
            'student_id' => $studentId, 'academic_session_id' => $sessionId, 'due_date' => '2026-12-31',
        ]);
        $invoiceCreate->assertStatus(201);
        $invoiceBody = $this->decode($invoiceCreate)['data'];
        // 5000 + 1500 (old route tier).
        $this->assertEquals(6500.0, $invoiceBody['total_amount']);
        $invoiceId = $invoiceBody['invoice_id'];

        $change = $this->withHeaders($headers)->withBodyFormat('json')->post("api/v1/transport/allocations/{$allocationId}/change-route", [
            'route_id'  => $newRouteId,
            'stop_name' => 'Stop B',
        ]);
        $change->assertStatus(200);
        $this->assertSame($newRouteId, $this->decode($change)['data']['route_id']);
        $show      = $this->withHeaders($headers)->get("api/v1/fees/invoices/{$invoiceId}");
        // 5000 + 2200 (new route tier).
        $this->assertEquals(7200.0, $this->decode($show)['data']['total_amount']);
    }
}
