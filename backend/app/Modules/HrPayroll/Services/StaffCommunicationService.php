<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Services;

use App\Modules\HrPayroll\Models\StaffCommunicationModel;
use App\Modules\HrPayroll\Models\EmployeeModel;
use App\Core\Authz\ModuleAuthorizer;

class StaffCommunicationService
{
    public function __construct(
        private readonly StaffCommunicationModel $communicationModel,
        private readonly EmployeeModel $employeeModel,
        private readonly ModuleAuthorizer $moduleAuthorizer
    ) {
    }

    public function getCommunications(): array
    {
        $this->moduleAuthorizer->assertAnyManage(['hr_payroll.manage', 'hr_payroll.view']);

        return $this->communicationModel
            ->orderBy('is_pinned', 'DESC')
            ->orderBy('publish_date', 'DESC')
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    public function createCommunication(array $data): array
    {
        $this->moduleAuthorizer->assertManage('hr_payroll.manage');

        $data['status'] = $data['status'] ?? 'Published';
        
        $id = $this->communicationModel->insert($data);
        return $this->communicationModel->find($id)->toArray();
    }

    public function getUpcomingEvents(): array
    {
        $this->moduleAuthorizer->assertAnyManage(['hr_payroll.manage', 'hr_payroll.view']);

        $employees = $this->employeeModel->where('status', 'Active')->findAll();
        $events = [];

        $today = new \DateTime();
        $currentMonth = (int) $today->format('m');
        $currentDay = (int) $today->format('d');
        $currentYear = (int) $today->format('Y');

        foreach ($employees as $emp) {
            $dobRaw = $this->extractDob($emp);
            if ($dobRaw) {
                $dob = new \DateTime($dobRaw);
                $m = (int) $dob->format('m');
                $d = (int) $dob->format('d');
                if ($m === $currentMonth && $d >= $currentDay) {
                    $events[] = [
                        'type' => 'Birthday',
                        'employee_name' => $emp->first_name . ' ' . $emp->last_name,
                        'department' => $emp->department,
                        'date' => $currentYear . '-' . str_pad((string)$m, 2, '0', STR_PAD_LEFT) . '-' . str_pad((string)$d, 2, '0', STR_PAD_LEFT),
                        'day' => $d
                    ];
                }
            }

            if ($emp->joining_date) {
                $jd = new \DateTime($emp->joining_date);
                $m = (int) $jd->format('m');
                $d = (int) $jd->format('d');
                $y = (int) $jd->format('Y');
                if ($m === $currentMonth && $d >= $currentDay && $y < $currentYear) {
                    $years = $currentYear - $y;
                    $events[] = [
                        'type' => 'Work Anniversary',
                        'employee_name' => $emp->first_name . ' ' . $emp->last_name,
                        'department' => $emp->department,
                        'date' => $currentYear . '-' . str_pad((string)$m, 2, '0', STR_PAD_LEFT) . '-' . str_pad((string)$d, 2, '0', STR_PAD_LEFT),
                        'day' => $d,
                        'years' => $years
                    ];
                }
            }
        }

        usort($events, fn($a, $b) => $a['day'] <=> $b['day']);
        return $events;
    }

    private function extractDob($emp): ?string
    {
        if (property_exists($emp, 'date_of_birth') && $emp->date_of_birth) {
            return $emp->date_of_birth;
        }
        
        // Sometimes it's in personal_info_json depending on the schema evolution
        if (property_exists($emp, 'personal_info_json') && $emp->personal_info_json) {
            $info = json_decode($emp->personal_info_json, true);
            if (isset($info['date_of_birth'])) {
                return $info['date_of_birth'];
            }
        }
        return null;
    }
}
