<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Modules\Admission\Models\SeatAllocationModel;
use Tests\Support\Admission\AdmissionTestCase;
use Tests\Support\Reports\ReportsExportAssertions;

/**
 * docs/ADR/ADR-022-reports-dashboard.md — report area 3.
 *
 * @internal
 */
final class AdmissionsFunnelTest extends AdmissionTestCase
{
    use ReportsExportAssertions;

    public function testAdmissionsFunnelComputesExactCountsAndOccupancy(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $sessionId = $this->createAcademicSession();
        $classId   = $this->createClassFixture();
        $this->createSeatAllocationFixture($classId, $sessionId, 40, 10);

        // Applications for this session's class.
        $this->createApplicationFixture($classId, 'SUBMITTED');
        $this->createApplicationFixture($classId, 'SUBMITTED');
        $this->createApplicationFixture($classId, 'VERIFIED');
        $this->createApplicationFixture($classId, 'ADMITTED');

        // A different class not part of this session must not be counted.
        $otherClassId = $this->createClassFixture();
        $this->createApplicationFixture($otherClassId, 'SUBMITTED');

        // Manually fill seats to check occupancy math (40 capacity, 4 filled = 10%).
        $seatAllocationModel = new SeatAllocationModel();
        $seatAllocation      = $seatAllocationModel->findByClassAndSession($classId, $sessionId);
        $seatAllocationModel->update($seatAllocation->seat_allocation_id, ['seats_filled' => 4]);

        $response = $this->withHeaders($headers)->get("api/v1/reports/admissions-funnel?academic_session_id={$sessionId}");

        $response->assertStatus(200);
        $body = $this->decode($response)['data'];

        $this->assertSame(2, $body['counts_by_status']['SUBMITTED']);
        $this->assertSame(1, $body['counts_by_status']['VERIFIED']);
        $this->assertSame(1, $body['counts_by_status']['ADMITTED']);
        $this->assertArrayNotHasKey('REJECTED', $body['counts_by_status']);

        $occupancy = $body['seat_occupancy_by_class'][0];
        $this->assertSame($classId, $occupancy['class_id']);
        $this->assertSame(40, $occupancy['total_capacity']);
        $this->assertSame(4, $occupancy['seats_filled']);
        $this->assertEquals(10.0, $occupancy['occupancy_percentage']);
    }

    public function testAdmissionsFunnelPdfExportProducesValidPdf(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $sessionId = $this->createAcademicSession();
        $this->createSeatAllocationFixture(null, $sessionId);

        $response = $this->withHeaders($headers)->get("api/v1/reports/admissions-funnel/pdf?academic_session_id={$sessionId}");

        $response->assertStatus(200);
        $body = $this->extractDownloadBinary($response);
        $this->assertNotEmpty($body);
        $this->assertSame('%PDF', substr($body, 0, 4));
    }

    public function testAdmissionsFunnelExcelExportProducesValidXlsx(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $sessionId = $this->createAcademicSession();
        $this->createSeatAllocationFixture(null, $sessionId);

        $response = $this->withHeaders($headers)->get("api/v1/reports/admissions-funnel/excel?academic_session_id={$sessionId}");

        $response->assertStatus(200);
        $body = $this->extractDownloadBinary($response);
        $this->assertNotEmpty($body);
        $this->assertSame("PK\x03\x04", substr($body, 0, 4));
    }
}
