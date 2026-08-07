<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/hr-payroll/Phase-1-Domain-Model.md — ENT-ATT-002.
 * Lives in Attendance per Appendix-G's own Module: ATT designation,
 * built alongside HR & Payroll per ADR-008 §3.
 *
 * @property int|null $staff_attendance_id
 * @property int      $employee_id
 * @property string   $attendance_date
 * @property string   $state
 * @property bool     $is_reconciled
 */
class StaffAttendanceRecord extends BaseEntity
{
    public const STATE_PRESENT      = 'Present';
    public const STATE_ON_LEAVE     = 'On Leave';
    public const STATE_UNAUTHORIZED = 'Unauthorized';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'staff_attendance_id' => 'integer',
            'employee_id'         => 'integer',
            'is_reconciled'       => 'boolean',
        ]);

        parent::__construct($data);
    }
}
