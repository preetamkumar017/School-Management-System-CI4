<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Services;

use App\Modules\HrPayroll\Models\StaffCommunicationModel;
use App\Modules\HrPayroll\Models\StaffCommunicationReadModel;
use App\Modules\HrPayroll\Models\EmployeeModel;
use App\Core\Authz\ModuleAuthorizer;

class StaffCommunicationService
{
    public function __construct(
        private readonly StaffCommunicationModel $communicationModel,
        private readonly StaffCommunicationReadModel $readModel,
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

    public function getActiveFeed(): array
    {
        // For all authenticated users
        $today = date('Y-m-d');
        
        $builder = $this->communicationModel
            ->where('status', 'Published')
            ->where('publish_date <=', $today)
            ->groupStart()
                ->where('expiry_date IS NULL', null, false)
                ->orWhere('expiry_date >=', $today)
            ->groupEnd()
            ->orderBy('is_pinned', 'DESC')
            ->orderBy('publish_date', 'DESC');

        return $builder->findAll();
    }

    public function getUnreadCommunications(int $userId): array
    {
        // First get all active communications for this user
        // (Currently target_audience logic is simple, let's just get all active first)
        $today = date('Y-m-d');
        
        $activeComms = $this->communicationModel
            ->where('status', 'Published')
            ->where('publish_date <=', $today)
            ->groupStart()
                ->where('expiry_date IS NULL', null, false)
                ->orWhere('expiry_date >=', $today)
            ->groupEnd()
            ->findAll();

        if (empty($activeComms)) {
            return [];
        }

        $activeIds = array_map(fn($c) => $c->communication_id, $activeComms);

        // Get reads by this user
        $reads = $this->readModel
            ->where('user_id', $userId)
            ->whereIn('communication_id', $activeIds)
            ->findAll();

        $readIds = array_map(fn($r) => $r['communication_id'], $reads);

        // Filter unread
        $unread = array_filter($activeComms, fn($c) => !in_array($c->communication_id, $readIds));

        // Sort: Alerts first, then pinned, then newest
        usort($unread, function($a, $b) {
            if ($a->type === 'Alert' && $b->type !== 'Alert') return -1;
            if ($b->type === 'Alert' && $a->type !== 'Alert') return 1;
            if ($a->is_pinned != $b->is_pinned) return $b->is_pinned <=> $a->is_pinned;
            return $b->publish_date <=> $a->publish_date;
        });

        return array_values($unread);
    }

    public function markAsRead(int $userId, int $communicationId): void
    {
        $existing = $this->readModel
            ->where('user_id', $userId)
            ->where('communication_id', $communicationId)
            ->first();

        if (!$existing) {
            $this->readModel->insert([
                'user_id' => $userId,
                'communication_id' => $communicationId,
                'read_at' => date('Y-m-d H:i:s')
            ]);
        }
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
