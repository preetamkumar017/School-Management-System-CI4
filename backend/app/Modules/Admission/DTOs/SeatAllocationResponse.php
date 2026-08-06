<?php

declare(strict_types=1);

namespace App\Modules\Admission\DTOs;

use App\Modules\Admission\Entities\SeatAllocation;

/**
 * docs/design/admission/Phase-3-DTO-Design.md
 */
final class SeatAllocationResponse
{
    public readonly int $seatAllocationId;
    public readonly int $classId;
    public readonly int $academicSessionId;
    public readonly int $totalCapacity;
    public readonly int $rteQuotaCapacity;
    public readonly int $seatsFilled;
    public readonly int $rteSeatsFilled;

    public function __construct(SeatAllocation $seatAllocation)
    {
        $this->seatAllocationId  = $seatAllocation->seat_allocation_id;
        $this->classId           = $seatAllocation->class_id;
        $this->academicSessionId = $seatAllocation->academic_session_id;
        $this->totalCapacity     = $seatAllocation->total_capacity;
        $this->rteQuotaCapacity  = $seatAllocation->rte_quota_capacity;
        $this->seatsFilled       = $seatAllocation->seats_filled;
        $this->rteSeatsFilled    = $seatAllocation->rte_seats_filled;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'seat_allocation_id'  => $this->seatAllocationId,
            'class_id'            => $this->classId,
            'academic_session_id' => $this->academicSessionId,
            'total_capacity'      => $this->totalCapacity,
            'rte_quota_capacity'  => $this->rteQuotaCapacity,
            'seats_filled'        => $this->seatsFilled,
            'rte_seats_filled'    => $this->rteSeatsFilled,
        ];
    }
}
