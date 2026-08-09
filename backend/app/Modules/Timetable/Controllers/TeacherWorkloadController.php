<?php

declare(strict_types=1);

namespace App\Modules\Timetable\Controllers;

use App\Core\BaseController;
use App\Modules\Timetable\Services\TeacherWorkloadService;
use App\Modules\Timetable\Models\TimetableEntryModel;
use App\Modules\Timetable\Models\SubstitutionModel;
use App\Modules\HrPayroll\Models\EmployeeModel;

class TeacherWorkloadController extends BaseController
{
    private TeacherWorkloadService $service;

    public function __construct()
    {
        $this->service = new TeacherWorkloadService(
            new TimetableEntryModel(),
            new SubstitutionModel(),
            new EmployeeModel()
        );
    }

    public function index()
    {
        $report = $this->service->getSchoolWorkloadReport();
        return $this->respondSuccess($report);
    }

    public function show(int $employeeId)
    {
        $details = $this->service->getTeacherWorkload($employeeId);
        return $this->respondSuccess($details);
    }

    public function freePeriods(int $employeeId, string $dayOfWeek)
    {
        // Simple day validation
        $validDays = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];
        if (!in_array(strtoupper($dayOfWeek), $validDays, true)) {
            return $this->failValidationErrors(['day' => 'Invalid day of week']);
        }

        $freePeriods = $this->service->getFreePeriods($employeeId, strtoupper($dayOfWeek));
        return $this->respondSuccess($freePeriods);
    }

    public function toggleExtraClass(int $entryId)
    {
        $model = new TimetableEntryModel();
        $entry = $model->find($entryId);
        if (!$entry) {
            return $this->failNotFound('Entry not found');
        }

        $model->update($entryId, [
            'is_extra_class' => !$entry->is_extra_class
        ]);

        return $this->respondSuccess(['is_extra_class' => !$entry->is_extra_class]);
    }
}
