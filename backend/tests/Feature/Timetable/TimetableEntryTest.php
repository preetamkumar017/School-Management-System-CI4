<?php

declare(strict_types=1);

namespace Tests\Feature\Timetable;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\HrPayroll\Models\DepartmentModel;
use App\Modules\HrPayroll\Models\DesignationModel;
use App\Modules\HrPayroll\Models\EmployeeModel;
use App\Modules\Timetable\Models\TimetableEntryModel;
use Tests\Support\Timetable\TimetableTestCase;

/**
 * @internal
 */
final class TimetableEntryTest extends TimetableTestCase
{
    /**
     * ADR-008 §2 — employee_id is now validated for real against HR &
     * Payroll's Employee, closing ADR-006 §1's stub.
     */
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

    public function testCreateAndPublishEntry(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $sectionId  = $this->createSection();
        $subjectId  = $this->createSubject();
        $employeeId = $this->createEmployeeId();

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/timetable/entries', [
            'section_id'  => $sectionId,
            'subject_id'  => $subjectId,
            'employee_id' => $employeeId,
            'day_of_week' => 'MONDAY',
            'period_no'   => 1,
        ]);
        $create->assertStatus(201);
        $entryId = $this->decode($create)['data']['timetable_entry_id'];
        $this->assertSame('DRAFT', $this->decode($create)['data']['status']);

        $publish = $this->withHeaders($headers)->post("api/v1/timetable/entries/{$entryId}/publish");
        $publish->assertStatus(200);
        $this->assertSame('PUBLISHED', $this->decode($publish)['data']['status']);
    }

    public function testTeacherDoubleBookingIsRejected(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $employeeId = $this->createEmployeeId();

        $this->createTimetableEntryFixture(null, null, $employeeId, 'TUESDAY', 3, 'DRAFT');

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/timetable/entries', [
                'section_id'  => $this->createSection(),
                'subject_id'  => $this->createSubject(),
                'employee_id' => $employeeId,
                'day_of_week' => 'TUESDAY',
                'period_no'   => 3,
            ]),
            BusinessRuleException::class,
            'TEACHER_DOUBLE_BOOKED',
            422,
        );
    }

    public function testRoomDoubleBookingIsRejected(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->createTimetableEntryFixture(null, null, $this->createEmployeeId(), 'WEDNESDAY', 4, 'DRAFT', 'LAB-1');

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/timetable/entries', [
                'section_id'  => $this->createSection(),
                'subject_id'  => $this->createSubject(),
                'employee_id' => $this->createEmployeeId(),
                'day_of_week' => 'WEDNESDAY',
                'period_no'   => 4,
                'room_id'     => 'LAB-1',
            ]),
            BusinessRuleException::class,
            'ROOM_DOUBLE_BOOKED',
            422,
        );
    }

    public function testReviseRequiresThePublishedStatus(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $employeeId = $this->createEmployeeId();
        $entryId    = $this->createTimetableEntryFixture(null, null, $employeeId, 'THURSDAY', 5, 'DRAFT');

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post("api/v1/timetable/entries/{$entryId}/revise", [
                'section_id'  => $this->createSection(),
                'subject_id'  => $this->createSubject(),
                'employee_id' => $employeeId,
                'day_of_week' => 'THURSDAY',
                'period_no'   => 6,
            ]),
            BusinessRuleException::class,
            'TIMETABLE_ENTRY_NOT_PUBLISHED',
            422,
        );
    }

    public function testReviseIncrementsVersionNumber(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $sectionId  = $this->createSection();
        $subjectId  = $this->createSubject();
        $employeeId = $this->createEmployeeId();
        $entryId    = $this->createTimetableEntryFixture($sectionId, $subjectId, $employeeId, 'FRIDAY', 2, 'PUBLISHED');

        $revise = $this->withHeaders($headers)->withBodyFormat('json')->post("api/v1/timetable/entries/{$entryId}/revise", [
            'section_id'  => $sectionId,
            'subject_id'  => $subjectId,
            'employee_id' => $employeeId,
            'day_of_week' => 'FRIDAY',
            'period_no'   => 7,
        ]);
        $revise->assertStatus(200);
        $body = $this->decode($revise)['data'];
        $this->assertSame(2, $body['version_no']);
        $this->assertSame(7, $body['period_no']);
    }

    public function testWeeklyLoadCeilingIsEnforced(): void
    {
        $user       = $this->createUser();
        $tokens     = $this->loginAs($user['username']);
        $headers    = $this->authHeaders($tokens['access_token']);
        $employeeId = $this->createEmployeeId();

        $model = new TimetableEntryModel();

        for ($period = 1; $period <= 30; $period++) {
            $model->insert([
                'section_id'  => $this->createSection(),
                'subject_id'  => $this->createSubject(),
                'employee_id' => $employeeId,
                'day_of_week' => 'MONDAY',
                'period_no'   => $period,
                'version_no'  => 1,
                'status'      => 'DRAFT',
            ]);
        }

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/timetable/entries', [
                'section_id'  => $this->createSection(),
                'subject_id'  => $this->createSubject(),
                'employee_id' => $employeeId,
                'day_of_week' => 'TUESDAY',
                'period_no'   => 1,
            ]),
            BusinessRuleException::class,
            'WEEKLY_LOAD_CEILING_EXCEEDED',
            422,
        );
    }
}
