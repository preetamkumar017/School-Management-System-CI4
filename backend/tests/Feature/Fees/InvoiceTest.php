<?php

declare(strict_types=1);

namespace Tests\Feature\Fees;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Fees\Models\FeeStructureModel;
use App\Modules\Transport\Models\RouteModel;
use App\Modules\Transport\Models\TransportAllocationModel;
use Tests\Support\Fees\FeesTestCase;

/**
 * @internal
 */
final class InvoiceTest extends FeesTestCase
{
    /**
     * The headline test for ADR-007 §1: total_amount is computed as the
     * sum of matching FeeStructure rows minus matching ScholarshipWaivers
     * — nothing is client-supplied.
     */
    public function testGenerateInvoiceComputesTotalFromFeeStructureMinusWaivers(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $classId   = $this->createClassFixture();
        $sectionId = $this->createSection($classId);
        $sessionId = $this->createAcademicSession();

        $tuitionHeadId   = $this->createFeeHeadFixture('Tuition ' . uniqid('', true));
        $transportHeadId = $this->createFeeHeadFixture('Transport ' . uniqid('', true));

        $this->createFeeStructureFixture($classId, $tuitionHeadId, $sessionId, 'GENERAL', 5000.0);
        $this->createFeeStructureFixture($classId, $transportHeadId, $sessionId, 'GENERAL', 2000.0);

        $studentId = $this->createStudentFixture(null, $sectionId, 'ACTIVE', 'GENERAL');
        $this->createScholarshipWaiverFixture($studentId, $tuitionHeadId, 'MERIT', 1000.0);

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/fees/invoices', [
            'student_id'          => $studentId,
            'academic_session_id' => $sessionId,
            'due_date'            => '2026-12-31',
        ]);
        $create->assertStatus(201);
        $body = $this->decode($create)['data'];

        // (5000 - 1000 waiver) + 2000 = 6000.
        $this->assertEquals(6000.0, $body['total_amount']);
        $this->assertSame('UNPAID', $body['status']);
        $this->assertFalse($body['is_locked']);
        $this->assertStringStartsWith('INV-', $body['invoice_no']);
    }

    /**
     * ADR-014 §1 (BR-FEE-003): a route-tier FeeStructure is automatically
     * folded into the total when the student has an active
     * TransportAllocation on that route — no manual FeeHead entry needed.
     */
    public function testGenerateInvoiceIncludesRouteTierFeeForAllocatedStudent(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $classId   = $this->createClassFixture();
        $sectionId = $this->createSection($classId);
        $sessionId = $this->createAcademicSession();

        $tuitionHeadId   = $this->createFeeHeadFixture('Tuition ' . uniqid('', true));
        $transportHeadId = $this->createFeeHeadFixture('Transport Tier ' . uniqid('', true));

        $routeId = (new RouteModel())->insert([
            'route_name' => 'Route ' . uniqid('', true),
            'stops_json' => ['Stop A'],
            'capacity'   => 10,
        ], true);

        $this->createFeeStructureFixture($classId, $tuitionHeadId, $sessionId, 'GENERAL', 5000.0);
        // Route-tier row — only applies to students allocated to $routeId.
        (new FeeStructureModel())->insert([
            'class_id'            => $classId,
            'fee_head_id'         => $transportHeadId,
            'academic_session_id' => $sessionId,
            'route_id'            => $routeId,
            'category'            => 'GENERAL',
            'amount'              => 1500.0,
        ], true);

        $studentId = $this->createStudentFixture(null, $sectionId, 'ACTIVE', 'GENERAL');

        (new TransportAllocationModel())->insert([
            'student_id'        => $studentId,
            'route_id'          => $routeId,
            'stop_name'         => 'Stop A',
            'emergency_contact' => '9876500000',
            'status'            => 'Active',
        ], true);

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/fees/invoices', [
            'student_id'          => $studentId,
            'academic_session_id' => $sessionId,
            'due_date'            => '2026-12-31',
        ]);
        $create->assertStatus(201);

        // 5000 tuition + 1500 route-tier transport fee.
        $this->assertEquals(6500.0, $this->decode($create)['data']['total_amount']);
    }

    /**
     * A route-tier fee row for a route the student is NOT allocated to
     * must never be pulled into their invoice.
     */
    public function testGenerateInvoiceExcludesRouteTierFeeForUnallocatedStudent(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $classId   = $this->createClassFixture();
        $sectionId = $this->createSection($classId);
        $sessionId = $this->createAcademicSession();

        $tuitionHeadId   = $this->createFeeHeadFixture('Tuition ' . uniqid('', true));
        $transportHeadId = $this->createFeeHeadFixture('Transport Tier ' . uniqid('', true));

        $routeId = (new RouteModel())->insert([
            'route_name' => 'Route ' . uniqid('', true),
            'stops_json' => ['Stop A'],
            'capacity'   => 10,
        ], true);

        $this->createFeeStructureFixture($classId, $tuitionHeadId, $sessionId, 'GENERAL', 5000.0);
        (new FeeStructureModel())->insert([
            'class_id'            => $classId,
            'fee_head_id'         => $transportHeadId,
            'academic_session_id' => $sessionId,
            'route_id'            => $routeId,
            'category'            => 'GENERAL',
            'amount'              => 1500.0,
        ], true);

        $studentId = $this->createStudentFixture(null, $sectionId, 'ACTIVE', 'GENERAL');

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/fees/invoices', [
            'student_id'          => $studentId,
            'academic_session_id' => $sessionId,
            'due_date'            => '2026-12-31',
        ]);
        $create->assertStatus(201);

        $this->assertEquals(5000.0, $this->decode($create)['data']['total_amount']);
    }

    public function testGenerateInvoiceRequiresAStudentSection(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $studentId = $this->createStudentFixture(null, null, 'DRAFT', 'GENERAL');
        $sessionId = $this->createAcademicSession();

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/fees/invoices', [
                'student_id'          => $studentId,
                'academic_session_id' => $sessionId,
                'due_date'            => '2026-12-31',
            ]),
            BusinessRuleException::class,
            'STUDENT_HAS_NO_SECTION',
            422,
        );
    }

    public function testApplyLateFeeIsIdempotent(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $invoiceId = $this->createInvoiceFixture(null, null, 1000.0);

        $apply = $this->withHeaders($headers)->post("api/v1/fees/invoices/{$invoiceId}/apply-late-fee");
        $apply->assertStatus(200);
        $this->assertEquals(1050.0, $this->decode($apply)['data']['total_amount']);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->post("api/v1/fees/invoices/{$invoiceId}/apply-late-fee"),
            BusinessRuleException::class,
            'LATE_FEE_ALREADY_APPLIED',
            422,
        );
    }

    public function testFlagOverdueAsDefaulter(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $invoiceId = $this->createInvoiceFixture(null, null, 1000.0, 'UNPAID', '2020-01-01');

        $flag = $this->withHeaders($headers)->post("api/v1/fees/invoices/{$invoiceId}/flag-defaulter");
        $flag->assertStatus(200);
        $this->assertSame('DEFAULTER', $this->decode($flag)['data']['status']);
    }

    public function testFlagDefaulterRejectsANotYetOverdueInvoice(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $invoiceId = $this->createInvoiceFixture(null, null, 1000.0, 'UNPAID', '2099-01-01');

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->post("api/v1/fees/invoices/{$invoiceId}/flag-defaulter"),
            BusinessRuleException::class,
            'INVOICE_NOT_OVERDUE',
            422,
        );
    }
}
