<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Attendance\Models\StaffPunchModel;
use CodeIgniter\I18n\Time;

class StaffPunchService
{
    public function __construct(
        private readonly StaffPunchModel $punchModel,
        private readonly AttendanceCalculationEngine $calculationEngine
    ) {
    }

    /**
     * Record a new punch from a biometric device or manual entry.
     */
    public function recordPunch(int $employeeId, string $punchTime, string $punchType, string $source = 'Biometric', ?string $deviceId = null): void
    {
        if (!in_array($punchType, ['In', 'Out'], true)) {
            throw new BusinessRuleException('INVALID_PUNCH_TYPE', 'Punch type must be In or Out.');
        }

        $this->punchModel->insert([
            'employee_id' => $employeeId,
            'punch_time'  => $punchTime,
            'punch_type'  => $punchType,
            'source'      => $source,
            'device_id'   => $deviceId,
        ]);

        $date = Time::parse($punchTime)->toDateString();

        $punches = $this->punchModel->where('employee_id', $employeeId)
            ->like('punch_time', $date, 'after')
            ->findAll();

        $this->calculationEngine->recalculate($employeeId, $date, $punches);
    }
}
