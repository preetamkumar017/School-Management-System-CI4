<?php

declare(strict_types=1);

namespace Tests\Feature\Fees;

use App\Core\Exceptions\BusinessRuleException;
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
