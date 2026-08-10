<?php

namespace App\Modules\Timetable\Services;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Modules\Timetable\Models\TimetableEntryModel;
use App\Modules\Timetable\Entities\TimetableEntry;

class TeacherWorkloadServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    protected $refresh = true;
    protected $namespace = 'App';

    public function testGetFreePeriodsReturnsCorrectPeriods()
    {
        $timetableModel = new TimetableEntryModel();
        
        // Employee 1 has classes on period 1, 3, and 5 on MONDAY
        $timetableModel->insert(new TimetableEntry([
            'section_id' => 1, 'subject_id' => 1, 'employee_id' => 1,
            'day_of_week' => 'MONDAY', 'period_no' => 1, 'version_no' => 1, 'status' => 'PUBLISHED'
        ]));
        $timetableModel->insert(new TimetableEntry([
            'section_id' => 1, 'subject_id' => 1, 'employee_id' => 1,
            'day_of_week' => 'MONDAY', 'period_no' => 3, 'version_no' => 1, 'status' => 'PUBLISHED'
        ]));
        $timetableModel->insert(new TimetableEntry([
            'section_id' => 1, 'subject_id' => 1, 'employee_id' => 1,
            'day_of_week' => 'MONDAY', 'period_no' => 5, 'version_no' => 1, 'status' => 'PUBLISHED'
        ]));

        // Assuming employeeModel and substitutionModel mocks or empty tables
        $service = new TeacherWorkloadService(
            $timetableModel,
            new \App\Modules\Timetable\Models\SubstitutionModel(),
            new \App\Modules\HrPayroll\Models\EmployeeModel(),
            \Config\Services::moduleAuthorizer()
        );

        $freePeriods = $service->getFreePeriods(1, 'MONDAY');

        $this->assertEquals([2, 4, 6, 7, 8], $freePeriods);
    }
}
