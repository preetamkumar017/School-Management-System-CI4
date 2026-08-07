<?php

declare(strict_types=1);

namespace Tests\Feature\Timetable;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Attendance\Models\StaffAttendanceRecordModel;
use App\Modules\Communication\Models\NotificationLogModel;
use App\Modules\HrPayroll\Models\DepartmentModel;
use App\Modules\HrPayroll\Models\DesignationModel;
use App\Modules\HrPayroll\Models\EmployeeModel;
use App\Modules\Timetable\Models\SubjectTeacherEligibilityModel;
use App\Modules\Timetable\Models\TimetableEntryModel;
use Tests\Support\Timetable\TimetableTestCase;

/**
 * @internal
 * BR-TT-004 / FR-16, ADR-013.
 */
final class SubstitutionTest extends TimetableTestCase
{
    private function createEmployeeId(): int
    {
        $departmentId  = (new DepartmentModel())->insert(['department_name' => 'Dept ' . uniqid('', true)], true);
        $designationId = (new DesignationModel())->insert(['designation_name' => 'Desig ' . uniqid('', true)], true);

        return (new EmployeeModel())->insert([
            'employee_code'         => 'EMP-' . random_int(100000, 999999),
            'full_name'             => 'Teacher ' . uniqid('', true),
            'department_id'         => $departmentId,
            'designation_id'        => $designationId,
            'joining_date'          => '2020-01-01',
            'salary_structure_json' => ['basic' => 30000],
            'status'                => 'Active',
        ], true);
    }

    private function markAbsent(int $employeeId, string $date): void
    {
        (new StaffAttendanceRecordModel())->insert([
            'employee_id'     => $employeeId,
            'attendance_date' => $date,
            'state'           => 'Unauthorized',
            'is_reconciled'   => false,
        ]);
    }

    public function testAssignsAnEligibleSubstituteAndLogsNotification(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $sectionId  = $this->createSection();
        $subjectId  = $this->createSubject();
        $absentId   = $this->createEmployeeId();
        $subId      = $this->createEmployeeId();
        $entryId    = $this->createTimetableEntryFixture($sectionId, $subjectId, $absentId, 'MONDAY', 1, 'PUBLISHED');

        (new SubjectTeacherEligibilityModel())->insert(['employee_id' => $subId, 'subject_id' => $subjectId]);
        $this->markAbsent($absentId, '2026-08-10');

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/timetable/substitutions', [
            'timetable_entry_id'      => $entryId,
            'substitution_date'       => '2026-08-10',
            'substitute_employee_id'  => $subId,
        ]);

        $create->assertStatus(201);
        $body = $this->decode($create)['data'];
        $this->assertSame('ASSIGNED', $body['status']);
        $this->assertSame($subId, $body['substitute_employee_id']);
        $this->assertSame($absentId, $body['absent_employee_id']);

        $logs = (new NotificationLogModel())->findByRecipient('Employee', $subId);
        $this->assertNotEmpty($logs);
        $this->assertSame('FR-16 substitution assigned', $logs[0]->trigger_event);

        // The originating timetable entry itself must be untouched —
        // date-scoped only, not a BR-TT-005 revision (ADR-013 §3).
        $entryRow = (new TimetableEntryModel())->find($entryId);
        $this->assertSame(1, $entryRow->version_no);
        $this->assertSame($absentId, $entryRow->employee_id);
    }

    public function testAutoPicksTheFirstEligibleAndAvailableSubstituteWhenNoneSupplied(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $subjectId  = $this->createSubject();
        $absentId   = $this->createEmployeeId();
        $subId      = $this->createEmployeeId();
        $entryId    = $this->createTimetableEntryFixture(null, $subjectId, $absentId, 'TUESDAY', 2, 'PUBLISHED');

        (new SubjectTeacherEligibilityModel())->insert(['employee_id' => $subId, 'subject_id' => $subjectId]);
        $this->markAbsent($absentId, '2026-08-11');

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/timetable/substitutions', [
            'timetable_entry_id' => $entryId,
            'substitution_date'  => '2026-08-11',
        ]);

        $create->assertStatus(201);
        $body = $this->decode($create)['data'];
        $this->assertSame('ASSIGNED', $body['status']);
        $this->assertSame($subId, $body['substitute_employee_id']);
    }

    public function testNoEligibleSubstituteFallsBackToUnsupervised(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $subjectId = $this->createSubject();
        $absentId  = $this->createEmployeeId();
        $entryId   = $this->createTimetableEntryFixture(null, $subjectId, $absentId, 'WEDNESDAY', 3, 'PUBLISHED');

        // No SubjectTeacherEligibility row for anyone -> no candidates.
        $this->markAbsent($absentId, '2026-08-12');

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/timetable/substitutions', [
            'timetable_entry_id' => $entryId,
            'substitution_date'  => '2026-08-12',
        ]);

        $create->assertStatus(201);
        $body = $this->decode($create)['data'];
        $this->assertSame('UNSUPERVISED', $body['status']);
        $this->assertNull($body['substitute_employee_id']);

        $logs = (new NotificationLogModel())->findByRecipient('Employee', $absentId);
        $this->assertNotEmpty($logs);
        $this->assertSame('FR-16 period unsupervised', $logs[0]->trigger_event);
    }

    public function testRejectedWhenTheAbsentTeacherWasNotActuallyRecordedAbsent(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $subjectId = $this->createSubject();
        $employeeId = $this->createEmployeeId();
        $entryId   = $this->createTimetableEntryFixture(null, $subjectId, $employeeId, 'THURSDAY', 4, 'PUBLISHED');

        // No StaffAttendanceRecord at all for this date.
        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/timetable/substitutions', [
                'timetable_entry_id' => $entryId,
                'substitution_date'  => '2026-08-13',
            ]),
            BusinessRuleException::class,
            'TEACHER_NOT_ABSENT',
            422,
        );
    }

    public function testRejectsAnExplicitlySuppliedButIneligibleSubstitute(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $subjectId  = $this->createSubject();
        $absentId   = $this->createEmployeeId();
        $ineligible = $this->createEmployeeId();
        $entryId    = $this->createTimetableEntryFixture(null, $subjectId, $absentId, 'FRIDAY', 5, 'PUBLISHED');

        $this->markAbsent($absentId, '2026-08-14');

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/timetable/substitutions', [
                'timetable_entry_id'     => $entryId,
                'substitution_date'      => '2026-08-14',
                'substitute_employee_id' => $ineligible,
            ]),
            BusinessRuleException::class,
            'SUBSTITUTE_NOT_ELIGIBLE',
            422,
        );
    }

    public function testEligibleSubstitutesEndpointListsCandidates(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $subjectId = $this->createSubject();
        $absentId  = $this->createEmployeeId();
        $subId     = $this->createEmployeeId();
        $entryId   = $this->createTimetableEntryFixture(null, $subjectId, $absentId, 'SATURDAY', 1, 'PUBLISHED');

        (new SubjectTeacherEligibilityModel())->insert(['employee_id' => $subId, 'subject_id' => $subjectId]);

        $list = $this->withHeaders($headers)->get("api/v1/timetable/entries/{$entryId}/eligible-substitutes");
        $list->assertStatus(200);
        $this->assertSame([$subId], $this->decode($list)['data']);
    }
}
