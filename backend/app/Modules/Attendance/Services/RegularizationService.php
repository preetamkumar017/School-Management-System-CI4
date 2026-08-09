<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Attendance\Models\RegularizationModel;
use App\Modules\Attendance\Models\StaffAttendanceRecordModel;
use CodeIgniter\I18n\Time;

class RegularizationService
{
    public function __construct(
        private readonly RegularizationModel $regularizationModel,
        private readonly StaffAttendanceRecordModel $attendanceRecordModel
    ) {
    }

    /**
     * Employee submits a regularization request.
     */
    public function requestRegularization(int $staffAttendanceId, string $requestedState, ?string $reason): void
    {
        $record = $this->attendanceRecordModel->find($staffAttendanceId);
        if (!$record) {
            throw new BusinessRuleException('RECORD_NOT_FOUND', 'Attendance record not found.');
        }

        $this->regularizationModel->insert([
            'staff_attendance_id' => $staffAttendanceId,
            'requested_state'     => $requestedState,
            'reason'              => $reason,
            'status'              => 'Pending',
        ]);
    }

    /**
     * HR approves or rejects a regularization request.
     */
    public function decide(int $regularizationId, string $status, int $approverId): void
    {
        if (!in_array($status, ['Approved', 'Rejected'], true)) {
            throw new BusinessRuleException('INVALID_STATUS', 'Status must be Approved or Rejected.');
        }

        $reg = $this->regularizationModel->find($regularizationId);
        if (!$reg) {
            throw new BusinessRuleException('NOT_FOUND', 'Regularization request not found.');
        }

        if ($reg->status !== 'Pending') {
            throw new BusinessRuleException('ALREADY_PROCESSED', 'Request has already been processed.');
        }

        $this->regularizationModel->update($regularizationId, [
            'status'      => $status,
            'approver_id' => $approverId,
            'approved_at' => Time::now()->toDateTimeString(),
        ]);

        if ($status === 'Approved') {
            $this->attendanceRecordModel->update($reg->staff_attendance_id, [
                'state' => $reg->requested_state,
            ]);
        }
    }
}
