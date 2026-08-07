<?php

declare(strict_types=1);

namespace Tests\Feature\Fees;

use App\Modules\Fees\Models\FeeStructureModel;
use App\Modules\Transport\Models\RouteModel;
use App\Modules\Transport\Models\TransportAllocationModel;
use Config\Services;
use Tests\Support\Fees\FeesTestCase;

/**
 * docs/ADR/ADR-020-fees-gst-line-items.md — BR-FEE-007.
 *
 * @internal
 */
final class InvoiceLineItemTest extends FeesTestCase
{
    /**
     * A mix of taxable and non-taxable fee heads: GST is computed only for
     * the taxable one, at its own configured rate, on top of its own
     * amount — total_amount remains the authoritative grand total.
     */
    public function testGenerateInvoiceComputesGstOnlyForTaxableFeeHeads(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $classId   = $this->createClassFixture();
        $sectionId = $this->createSection($classId);
        $sessionId = $this->createAcademicSession();

        $taxableHeadId    = $this->createFeeHeadFixture('Taxable Head ' . uniqid('', true), true, 18.0);
        $nonTaxableHeadId = $this->createFeeHeadFixture('Non-Taxable Head ' . uniqid('', true), false, null);

        $this->createFeeStructureFixture($classId, $taxableHeadId, $sessionId, 'GENERAL', 1000.0);
        $this->createFeeStructureFixture($classId, $nonTaxableHeadId, $sessionId, 'GENERAL', 500.0);

        $studentId = $this->createStudentFixture(null, $sectionId, 'ACTIVE', 'GENERAL');

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/fees/invoices', [
            'student_id'          => $studentId,
            'academic_session_id' => $sessionId,
            'due_date'            => '2026-12-31',
        ]);
        $create->assertStatus(201);
        $invoiceId = $this->decode($create)['data']['invoice_id'];

        // 1000 taxable + 180 GST (18%) + 500 non-taxable = 1680.
        $this->assertEquals(1680.0, $this->decode($create)['data']['total_amount']);

        $lineItems = $this->withHeaders($headers)->get("api/v1/fees/invoices/{$invoiceId}/line-items");
        $lineItems->assertStatus(200);
        $rows = $this->decode($lineItems)['data'];
        $this->assertCount(2, $rows);

        $byFeeHead = [];

        foreach ($rows as $row) {
            $byFeeHead[$row['fee_head_id']] = $row;
        }

        $taxableRow = $byFeeHead[$taxableHeadId];
        $this->assertEquals(1000.0, $taxableRow['base_amount']);
        $this->assertEquals(1000.0, $taxableRow['taxable_amount']);
        $this->assertEquals(18.0, $taxableRow['gst_rate']);
        $this->assertEquals(180.0, $taxableRow['gst_amount']);
        $this->assertEquals(1180.0, $taxableRow['line_total']);

        $nonTaxableRow = $byFeeHead[$nonTaxableHeadId];
        $this->assertEquals(500.0, $nonTaxableRow['base_amount']);
        $this->assertNull($nonTaxableRow['gst_rate']);
        $this->assertEquals(0.0, $nonTaxableRow['gst_amount']);
        $this->assertEquals(500.0, $nonTaxableRow['line_total']);
    }

    /**
     * ADR-020 §c: GST is computed on the post-waiver (net) amount, not the
     * pre-waiver base — a taxable ₹1000 head with a ₹400 waiver yields a
     * ₹600 taxable base, GST at 18% = ₹108, line total ₹708.
     */
    public function testGstIsComputedOnPostWaiverAmount(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $classId   = $this->createClassFixture();
        $sectionId = $this->createSection($classId);
        $sessionId = $this->createAcademicSession();

        $taxableHeadId = $this->createFeeHeadFixture('Taxable Waived Head ' . uniqid('', true), true, 18.0);
        $this->createFeeStructureFixture($classId, $taxableHeadId, $sessionId, 'GENERAL', 1000.0);

        $studentId = $this->createStudentFixture(null, $sectionId, 'ACTIVE', 'GENERAL');
        $this->createScholarshipWaiverFixture($studentId, $taxableHeadId, 'MERIT', 400.0);

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/fees/invoices', [
            'student_id'          => $studentId,
            'academic_session_id' => $sessionId,
            'due_date'            => '2026-12-31',
        ]);
        $create->assertStatus(201);
        $invoiceId = $this->decode($create)['data']['invoice_id'];

        // (1000 - 400 waiver) = 600 taxable, GST 18% = 108, line total 708.
        $this->assertEquals(708.0, $this->decode($create)['data']['total_amount']);

        $lineItems = $this->decode($this->withHeaders($headers)->get("api/v1/fees/invoices/{$invoiceId}/line-items"))['data'];
        $this->assertCount(1, $lineItems);
        $row = $lineItems[0];
        $this->assertEquals(1000.0, $row['base_amount']);
        $this->assertEquals(400.0, $row['waiver_amount']);
        $this->assertEquals(600.0, $row['taxable_amount']);
        $this->assertEquals(108.0, $row['gst_amount']);
        $this->assertEquals(708.0, $row['line_total']);
    }

    public function testGenerateInvoicePdfSucceedsWithLineItems(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $classId   = $this->createClassFixture();
        $sectionId = $this->createSection($classId);
        $sessionId = $this->createAcademicSession();

        $taxableHeadId = $this->createFeeHeadFixture('PDF Taxable Head ' . uniqid('', true), true, 12.0);
        $this->createFeeStructureFixture($classId, $taxableHeadId, $sessionId, 'GENERAL', 2000.0);

        $studentId = $this->createStudentFixture(null, $sectionId, 'ACTIVE', 'GENERAL');

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/fees/invoices', [
            'student_id'          => $studentId,
            'academic_session_id' => $sessionId,
            'due_date'            => '2026-12-31',
        ]);
        $create->assertStatus(201);
        $invoiceId = $this->decode($create)['data']['invoice_id'];

        $pdf = $this->withHeaders($headers)->post("api/v1/fees/invoices/{$invoiceId}/generate-pdf");
        $pdf->assertStatus(201);
        $body = $this->decode($pdf)['data'];
        $this->assertSame('Invoice', $body['owner_type']);
        $this->assertSame($invoiceId, $body['owner_ref_id']);
    }

    /**
     * ADR-014 §1's route-tier recalculation trigger must regenerate line
     * items consistently, not just the total.
     */
    public function testRecalculateForRouteChangeRegeneratesLineItems(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $classId   = $this->createClassFixture();
        $sectionId = $this->createSection($classId);
        $sessionId = $this->createAcademicSession();

        $tuitionHeadId = $this->createFeeHeadFixture('Tuition ' . uniqid('', true));
        $routeHeadId   = $this->createFeeHeadFixture('Route Tier ' . uniqid('', true), true, 10.0);

        $routeId = (new RouteModel())->insert([
            'route_name' => 'Route ' . uniqid('', true),
            'stops_json' => ['Stop A'],
            'capacity'   => 10,
        ], true);

        $this->createFeeStructureFixture($classId, $tuitionHeadId, $sessionId, 'GENERAL', 5000.0);
        (new FeeStructureModel())->insert([
            'class_id'            => $classId,
            'fee_head_id'         => $routeHeadId,
            'academic_session_id' => $sessionId,
            'route_id'            => $routeId,
            'category'            => 'GENERAL',
            'amount'              => 1000.0,
        ], true);

        $studentId = $this->createStudentFixture(null, $sectionId, 'ACTIVE', 'GENERAL');

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/fees/invoices', [
            'student_id'          => $studentId,
            'academic_session_id' => $sessionId,
            'due_date'            => '2026-12-31',
        ]);
        $create->assertStatus(201);
        $invoiceId = $this->decode($create)['data']['invoice_id'];

        // Before allocation: only tuition, no route-tier line item.
        $this->assertEquals(5000.0, $this->decode($create)['data']['total_amount']);
        $before = $this->decode($this->withHeaders($headers)->get("api/v1/fees/invoices/{$invoiceId}/line-items"))['data'];
        $this->assertCount(1, $before);

        (new TransportAllocationModel())->insert([
            'student_id'        => $studentId,
            'route_id'          => $routeId,
            'stop_name'         => 'Stop A',
            'emergency_contact' => '9876500000',
            'status'            => 'Active',
        ], true);

        Services::invoiceService()->recalculateForRouteChange($studentId, $routeId);

        $after = $this->decode($this->withHeaders($headers)->get("api/v1/fees/invoices/{$invoiceId}/line-items"))['data'];
        $this->assertCount(2, $after);

        $byFeeHead = [];

        foreach ($after as $row) {
            $byFeeHead[$row['fee_head_id']] = $row;
        }

        $routeRow = $byFeeHead[$routeHeadId];
        // 1000 base, GST 10% = 100, line total 1100.
        $this->assertEquals(1100.0, $routeRow['line_total']);

        $invoiceAfter = $this->decode($this->withHeaders($headers)->get("api/v1/fees/invoices/{$invoiceId}"))['data'];
        // 5000 tuition + 1000 route-tier + 100 GST = 6100.
        $this->assertEquals(6100.0, $invoiceAfter['total_amount']);
    }
}
