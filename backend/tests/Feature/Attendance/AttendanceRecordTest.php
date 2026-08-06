<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Exceptions\ValidationException;
use Tests\Support\Attendance\AttendanceTestCase;

/**
 * @internal
 */
final class AttendanceRecordTest extends AttendanceTestCase
{
    public function testMarkAndLockAttendance(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $studentId = $this->createStudentFixture();
        $entryId   = $this->createTimetableEntryFixture(null, null, null, 'MONDAY', null, 'PUBLISHED');

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/attendance/records', [
            'student_id'         => $studentId,
            'timetable_entry_id' => $entryId,
            'attendance_date'    => date('Y-m-d'),
            'state'              => 'PRESENT',
        ]);
        $create->assertStatus(201);
        $recordId = $this->decode($create)['data']['attendance_record_id'];

        $lock = $this->withHeaders($headers)->post("api/v1/attendance/records/{$recordId}/lock");
        $lock->assertStatus(200);
        $this->assertTrue($this->decode($lock)['data']['is_locked']);
    }

    public function testDuplicateAttendanceIsRejected(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $studentId = $this->createStudentFixture();
        $entryId   = $this->createTimetableEntryFixture(null, null, null, 'MONDAY', null, 'PUBLISHED');
        $date      = date('Y-m-d');

        $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/attendance/records', [
            'student_id'         => $studentId,
            'timetable_entry_id' => $entryId,
            'attendance_date'    => $date,
            'state'              => 'PRESENT',
        ])->assertStatus(201);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/attendance/records', [
                'student_id'         => $studentId,
                'timetable_entry_id' => $entryId,
                'attendance_date'    => $date,
                'state'              => 'ABSENT',
            ]),
            BusinessRuleException::class,
            'ATTENDANCE_ALREADY_MARKED',
            422,
        );
    }

    public function testAttendanceRequiresAPublishedTimetableEntry(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $entryId   = $this->createTimetableEntryFixture(null, null, null, 'MONDAY', null, 'DRAFT');

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/attendance/records', [
                'student_id'         => $this->createStudentFixture(),
                'timetable_entry_id' => $entryId,
                'attendance_date'    => date('Y-m-d'),
                'state'              => 'PRESENT',
            ]),
            BusinessRuleException::class,
            'TIMETABLE_ENTRY_NOT_PUBLISHED',
            422,
        );
    }

    public function testCorrectionPastTheSameDayRequiresAReason(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $recordId  = $this->createAttendanceRecordFixture(null, null, '2020-01-01', 'ABSENT');

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')
                ->post("api/v1/attendance/records/{$recordId}/correct", ['state' => 'PRESENT']),
            ValidationException::class,
            'VALIDATION_FAILED',
            422,
        );

        $withReason = $this->withHeaders($headers)->withBodyFormat('json')
            ->post("api/v1/attendance/records/{$recordId}/correct", [
                'state'  => 'PRESENT',
                'reason' => 'Corrected after teacher review.',
            ]);
        $withReason->assertStatus(200);
        $this->assertSame('PRESENT', $this->decode($withReason)['data']['state']);
    }

    public function testCorrectionOnTheSameDayDoesNotRequireAReason(): void
    {
        $user     = $this->createUser();
        $tokens   = $this->loginAs($user['username']);
        $headers  = $this->authHeaders($tokens['access_token']);
        $recordId = $this->createAttendanceRecordFixture(null, null, date('Y-m-d'), 'ABSENT');

        $correct = $this->withHeaders($headers)->withBodyFormat('json')
            ->post("api/v1/attendance/records/{$recordId}/correct", ['state' => 'PRESENT']);
        $correct->assertStatus(200);
        $this->assertSame('PRESENT', $this->decode($correct)['data']['state']);
    }

    public function testAttendancePercentageAndEligibilityFlag(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $studentId = $this->createStudentFixture();

        // 1 present out of 4 marked periods = 25% — below the 75% threshold.
        $this->createAttendanceRecordFixture($studentId, null, '2026-01-01', 'PRESENT');
        $this->createAttendanceRecordFixture($studentId, null, '2026-01-02', 'ABSENT');
        $this->createAttendanceRecordFixture($studentId, null, '2026-01-03', 'ABSENT');
        $this->createAttendanceRecordFixture($studentId, null, '2026-01-04', 'ABSENT');

        $response = $this->withHeaders($headers)->get(
            "api/v1/attendance/records/percentage?student_id={$studentId}&from_date=2026-01-01&to_date=2026-01-31",
        );
        $response->assertStatus(200);
        $body = $this->decode($response)['data'];
        $this->assertEquals(25.0, $body['percentage']);
        $this->assertTrue($body['is_exam_eligibility_at_risk']);
    }
}
