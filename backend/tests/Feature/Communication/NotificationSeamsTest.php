<?php

declare(strict_types=1);

namespace Tests\Feature\Communication;

use App\Modules\Communication\Models\NotificationLogModel;
use App\Modules\HrPayroll\Models\DepartmentModel;
use App\Modules\HrPayroll\Models\DesignationModel;
use App\Modules\HrPayroll\Models\EmployeeModel;
use Tests\Support\Communication\CommunicationTestCase;

/**
 * docs/ADR/ADR-010-communication-and-reports-scope-decisions.md §3 —
 * closes ADR-006 §6/§9's logging-only notification seams.
 *
 * @internal
 */
final class NotificationSeamsTest extends CommunicationTestCase
{
    private function createEmployeeId(): int
    {
        $departmentId  = (new DepartmentModel())->insert(['department_name' => 'Dept ' . uniqid('', true)], true);
        $designationId = (new DesignationModel())->insert(['designation_name' => 'Desig ' . uniqid('', true)], true);

        return (new EmployeeModel())->insert([
            'employee_code'          => 'EMP-' . random_int(100000, 999999),
            'full_name'              => 'Teacher ' . uniqid('', true),
            'department_id'          => $departmentId,
            'designation_id'         => $designationId,
            'joining_date'           => '2020-01-01',
            'salary_structure_json'  => ['basic' => 30000],
            'status'                 => 'Active',
        ], true);
    }

    /**
     * BR-TT-005: revising a published timetable entry logs a Queued
     * notification to the assigned teacher.
     */
    public function testRevisingTimetableEntryLogsNotification(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $sectionId  = $this->createSection();
        $subjectId  = $this->createSubject();
        $employeeId = $this->createEmployeeId();
        $entryId    = $this->createTimetableEntryFixture($sectionId, $subjectId, $employeeId, 'MONDAY', 3, 'PUBLISHED');

        $revise = $this->withHeaders($headers)->withBodyFormat('json')->post("api/v1/timetable/entries/{$entryId}/revise", [
            'section_id'  => $sectionId,
            'subject_id'  => $subjectId,
            'employee_id' => $employeeId,
            'day_of_week' => 'MONDAY',
            'period_no'   => 4,
        ]);
        $revise->assertStatus(200);

        $logs = (new NotificationLogModel())->findByRecipient('Employee', $employeeId);
        $this->assertNotEmpty($logs);
        $this->assertSame('Queued', $logs[0]->status);
        $this->assertSame('BR-TT-005 timetable revision', $logs[0]->trigger_event);
    }

    /**
     * BR-ATT-004: marking a student ABSENT logs a Queued notification to
     * their primary guardian.
     */
    public function testMarkingAbsentLogsGuardianNotification(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $studentId  = $this->createStudentFixture();
        $guardianId = $this->createGuardianFixture();
        $this->linkGuardianToStudent($studentId, $guardianId, true);
        $entryId = $this->createTimetableEntryFixture(null, null, $this->createEmployeeId(), 'TUESDAY', 2, 'PUBLISHED');

        $mark = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/attendance/records', [
            'student_id'         => $studentId,
            'timetable_entry_id' => $entryId,
            'attendance_date'    => date('Y-m-d'),
            'state'              => 'ABSENT',
        ]);
        $mark->assertStatus(201);

        $logs = (new NotificationLogModel())->findByRecipient('Guardian', $guardianId);
        $this->assertNotEmpty($logs);
        $this->assertSame('Queued', $logs[0]->status);
        $this->assertSame('BR-ATT-004 absence alert', $logs[0]->trigger_event);
    }

    public function testMarkingPresentDoesNotLogNotification(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $studentId  = $this->createStudentFixture();
        $guardianId = $this->createGuardianFixture();
        $this->linkGuardianToStudent($studentId, $guardianId, true);
        $entryId = $this->createTimetableEntryFixture(null, null, $this->createEmployeeId(), 'WEDNESDAY', 2, 'PUBLISHED');

        $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/attendance/records', [
            'student_id'         => $studentId,
            'timetable_entry_id' => $entryId,
            'attendance_date'    => date('Y-m-d'),
            'state'              => 'PRESENT',
        ])->assertStatus(201);

        $logs = (new NotificationLogModel())->findByRecipient('Guardian', $guardianId);
        $this->assertEmpty($logs);
    }
}
