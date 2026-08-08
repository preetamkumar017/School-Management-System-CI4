<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Modules\Fees\Models\PaymentModel;
use Tests\Support\Fees\FeesTestCase;
use Tests\Support\Reports\ReportsExportAssertions;

/**
 * docs/ADR/ADR-022-reports-dashboard.md — report area 1.
 *
 * @internal
 */
final class FeeCollectionSummaryTest extends FeesTestCase
{
    use ReportsExportAssertions;

    public function testFeeCollectionSummaryComputesExactTotals(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $sessionId = $this->createAcademicSession();
        $classId   = $this->createClassFixture();
        $sectionId = $this->createSection($classId);
        $studentA  = $this->createStudentFixture(null, $sectionId);
        $studentB  = $this->createStudentFixture(null, $sectionId);

        // Invoice A: fully paid — 5000 collected, 0 outstanding.
        $invoiceA = $this->createInvoiceFixture($studentA, $sessionId, 5000.0, 'PAID');
        $this->createPaymentFixture($invoiceA, 5000.0, 'SUCCESS');

        // Invoice B: partially paid — 2000 collected, 3000 outstanding (8000 - 2000... wait use round numbers).
        $invoiceB = $this->createInvoiceFixture($studentB, $sessionId, 8000.0, 'PARTIALLY_PAID');
        $this->createPaymentFixture($invoiceB, 3000.0, 'SUCCESS');

        // Invoice C: unpaid, flagged DEFAULTER — 0 collected, full 4000 outstanding.
        $invoiceC = $this->createInvoiceFixture($studentA, $sessionId, 4000.0, 'DEFAULTER');

        // A FAILED payment must never count toward collected.
        (new PaymentModel())->insert([
            'invoice_id'              => $invoiceB,
            'amount_paid'             => 999.0,
            'payment_mode'            => 'CASH',
            'gateway_transaction_ref' => null,
            'paid_at'                 => date('Y-m-d H:i:s'),
            'status'                  => 'FAILED',
        ]);

        $response = $this->withHeaders($headers)->get("api/v1/reports/fee-collection?academic_session_id={$sessionId}");

        $response->assertStatus(200);
        $body = $this->decode($response)['data'];

        $this->assertEquals(8000.0, $body['total_collected']); // 5000 + 3000
        $this->assertEquals(9000.0, $body['total_outstanding']); // (8000-3000) + (4000-0)
        $this->assertSame(1, $body['defaulter_count']);
        $this->assertEquals(8000.0, $body['collected_by_class'][(string) $classId] ?? $body['collected_by_class'][$classId]);
        $this->assertEquals(9000.0, $body['outstanding_by_class'][(string) $classId] ?? $body['outstanding_by_class'][$classId]);
    }

    public function testFeeCollectionPdfExportProducesValidPdf(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $sessionId = $this->createAcademicSession();
        $this->createInvoiceFixture(null, $sessionId, 5000.0, 'UNPAID');

        $response = $this->withHeaders($headers)->get("api/v1/reports/fee-collection/pdf?academic_session_id={$sessionId}");

        $response->assertStatus(200);
        $body = $this->extractDownloadBinary($response);
        $this->assertNotEmpty($body);
        $this->assertSame('%PDF', substr($body, 0, 4));
    }

    public function testFeeCollectionExcelExportProducesValidXlsx(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $sessionId = $this->createAcademicSession();
        $this->createInvoiceFixture(null, $sessionId, 5000.0, 'UNPAID');

        $response = $this->withHeaders($headers)->get("api/v1/reports/fee-collection/excel?academic_session_id={$sessionId}");

        $response->assertStatus(200);
        $body = $this->extractDownloadBinary($response);
        $this->assertNotEmpty($body);
        // .xlsx is a ZIP container — PK\x03\x04 magic bytes.
        $this->assertSame("PK\x03\x04", substr($body, 0, 4));
    }
}
