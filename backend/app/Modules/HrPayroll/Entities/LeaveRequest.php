<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/hr-payroll/Phase-1-Domain-Model.md — ENT-HR-005.
 *
 * @property int|null    $leave_request_id
 * @property int         $employee_id
 * @property string      $leave_type
 * @property string      $start_date
 * @property string      $end_date
 * @property string|null $reason
 * @property string|null $duty_leave_reference
 * @property string      $status
 * @property int|null    $approver_id
 */
class LeaveRequest extends BaseEntity
{
    public const TYPE_CL  = 'CL';
    public const TYPE_SL  = 'SL';
    public const TYPE_EL  = 'EL';
    public const TYPE_ML  = 'ML';
    public const TYPE_LWP = 'LWP';
    public const TYPE_DL  = 'DL';

    public const STATUS_PENDING  = 'Pending';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_REJECTED = 'Rejected';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'leave_request_id' => 'integer',
            'employee_id'      => 'integer',
            'approver_id'      => '?integer',
        ]);

        parent::__construct($data);
    }
}
