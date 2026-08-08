<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Tests\Support\Attendance\AttendanceTestCase;
use Tests\Support\Reports\ReportsExportAssertions;

/**
 * docs/ADR/ADR-022-reports-dashboard.md — report area 2.
 *
 * @internal
 */
final class AttendanceOverviewTest extends AttendanceTestCase
{
    use ReportsExportAssertions;

    public function testAttendanceOverviewComputesExactPercentages(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $date      = date('Y-m-d');
        $classId   = $this->createClassFixture();
        $sectionId = $this->createSection($classId);

        // Student A: 3 present, 1 absent -> 75%.
        $studentA = $this->createStudentFixture(null, $sectionId);
        $entry1   = $this->createTimetableEntryFixture();
        $entry2   = $this->createTimetableEntryFixture();
        $entry3   = $this->createTimetableEntryFixture();
        $entry4   = $this->createTimetableEntryFixture();
        $this->createAttendanceRecordFixture($studentA, $entry1, $date, 'PRESENT');
        $this->createAttendanceRecordFixture($studentA, $entry2, $date, 'PRESENT');
        $this->createAttendanceRecordFixture($studentA, $entry3, $date, 'LATE');
        $this->createAttendanceRecordFixture($studentA, $entry4, $date, 'ABSENT');

        // Student B: 1 present, 1 absent -> 50%, below the 75% default threshold.
        $studentB = $this->createStudentFixture(null, $sectionId);
        $entry5   = $this->createTimetableEntryFixture();
        $entry6   = $this->createTimetableEntryFixture();
        $this->createAttendanceRecordFixture($studentB, $entry5, $date, 'PRESENT');
        $this->createAttendanceRecordFixture($studentB, $entry6, $date, 'ABSENT');

        $response = $this->withHeaders($headers)->get("api/v1/reports/attendance-overview?from_date={$date}&to_date={$date}");

        $response->assertStatus(200);
        $body = $this->decode($response)['data'];

        // School-wide: 4 present-or-late out of 6 total records = 66.67%.
        $this->assertEquals(66.67, $body['school_wide_percentage']);
        $this->assertEquals(75.0, $body['eligibility_threshold']);

        $classPercentage = $body['percentage_by_class'][(string) $classId] ?? $body['percentage_by_class'][$classId];
        $this->assertEquals(66.67, $classPercentage);

        $belowThresholdIds = array_column($body['students_below_threshold'], 'student_id');
        $this->assertContains($studentB, $belowThresholdIds);
        $this->assertNotContains($studentA, $belowThresholdIds);
    }

    public function testAttendanceOverviewPdfExportProducesValidPdf(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);
        $date    = date('Y-m-d');
        $this->createAttendanceRecordFixture(null, null, $date, 'PRESENT');

        $response = $this->withHeaders($headers)->get("api/v1/reports/attendance-overview/pdf?from_date={$date}&to_date={$date}");

        $response->assertStatus(200);
        $body = $this->extractDownloadBinary($response);
        $this->assertNotEmpty($body);
        $this->assertSame('%PDF', substr($body, 0, 4));
    }

    public function testAttendanceOverviewExcelExportProducesValidXlsx(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);
        $date    = date('Y-m-d');
        $this->createAttendanceRecordFixture(null, null, $date, 'PRESENT');

        $response = $this->withHeaders($headers)->get("api/v1/reports/attendance-overview/excel?from_date={$date}&to_date={$date}");

        $response->assertStatus(200);
        $body = $this->extractDownloadBinary($response);
        $this->assertNotEmpty($body);
        $this->assertSame("PK\x03\x04", substr($body, 0, 4));
    }
}
