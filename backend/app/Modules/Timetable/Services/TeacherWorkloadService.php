<?php

declare(strict_types=1);

namespace App\Modules\Timetable\Services;

use App\Modules\Timetable\Models\TimetableEntryModel;
use App\Modules\Timetable\Models\SubstitutionModel;
use App\Modules\HrPayroll\Models\EmployeeModel;

use App\Core\Authz\ModuleAuthorizer;

class TeacherWorkloadService
{
    public function __construct(
        private readonly TimetableEntryModel $timetableEntryModel,
        private readonly SubstitutionModel $substitutionModel,
        private readonly EmployeeModel $employeeModel,
        private readonly ModuleAuthorizer $moduleAuthorizer
    ) {
    }

    /**
     * Gets workload overview for all teaching staff.
     */
    public function getSchoolWorkloadReport(): array
    {
        $this->moduleAuthorizer->assertAnyManage(['timetable.manage', 'hr_payroll.manage']);
        
        $teachers = $this->employeeModel->where('status', 'Active')->findAll();
        $report = [];

        foreach ($teachers as $teacher) {
            $entries = $this->timetableEntryModel->where('employee_id', $teacher->employee_id)
                                                 ->where('status', 'PUBLISHED')
                                                 ->findAll();
            
            $substitutions = $this->substitutionModel->where('substitute_employee_id', $teacher->employee_id)
                                                     ->where('status', 'Approved')
                                                     ->findAll();

            $totalPeriods = count($entries);
            $extraClasses = count(array_filter($entries, fn($e) => $e->is_extra_class));
            $regularClasses = $totalPeriods - $extraClasses;

            // Basic calculation for optimal/overloaded status
            $status = 'Optimal';
            if ($totalPeriods > 30) {
                $status = 'Overloaded';
            } elseif ($totalPeriods < 15) {
                $status = 'Under-utilized';
            }

            $report[] = [
                'employee_id' => $teacher->employee_id,
                'first_name'  => $teacher->first_name,
                'last_name'   => $teacher->last_name,
                'department'  => $teacher->department ?? '-',
                'total_periods' => $totalPeriods,
                'regular_classes' => $regularClasses,
                'extra_classes' => $extraClasses,
                'substitutions' => count($substitutions),
                'status' => $status
            ];
        }

        return $report;
    }

    /**
     * Gets detailed workload for a single teacher
     */
    public function getTeacherWorkload(int $employeeId): array
    {
        $this->moduleAuthorizer->assertAnyManage(['timetable.manage', 'hr_payroll.manage']);

        $entries = $this->timetableEntryModel->where('employee_id', $employeeId)
                                             ->where('status', 'PUBLISHED')
                                             ->findAll();
        
        $substitutions = $this->substitutionModel->where('substitute_employee_id', $employeeId)
                                                 ->where('status', 'Approved')
                                                 ->findAll();

        return [
            'entries' => $entries,
            'substitutions' => $substitutions,
            'total_periods' => count($entries),
            'extra_classes' => count(array_filter($entries, fn($e) => $e->is_extra_class)),
        ];
    }

    /**
     * Gets free periods for a specific teacher on a specific day
     * Assuming 8 periods in a day (1 to 8).
     */
    public function getFreePeriods(int $employeeId, string $dayOfWeek): array
    {
        $this->moduleAuthorizer->assertAnyManage(['timetable.manage', 'hr_payroll.manage']);

        $entries = $this->timetableEntryModel->where('employee_id', $employeeId)
                                             ->where('day_of_week', $dayOfWeek)
                                             ->where('status', 'PUBLISHED')
                                             ->findAll();

        $occupiedPeriods = array_map(fn($e) => (int)$e->period_no, $entries);
        $totalPeriodsInDay = 8;
        
        $freePeriods = [];
        for ($i = 1; $i <= $totalPeriodsInDay; $i++) {
            if (!in_array($i, $occupiedPeriods, true)) {
                $freePeriods[] = $i;
            }
        }

        return $freePeriods;
    }
}
