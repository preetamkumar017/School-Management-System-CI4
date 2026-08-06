<?php

declare(strict_types=1);

namespace App\Modules\Admission\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/admission/Phase-1-Domain-Model.md — ENT-ADM-002.
 *
 * @property int|null $seat_allocation_id
 * @property int      $class_id
 * @property int      $academic_session_id
 * @property int      $total_capacity
 * @property int      $rte_quota_capacity
 * @property int      $seats_filled
 * @property int      $rte_seats_filled
 */
class SeatAllocation extends BaseEntity
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'seat_allocation_id'  => 'integer',
            'class_id'            => 'integer',
            'academic_session_id' => 'integer',
            'total_capacity'      => 'integer',
            'rte_quota_capacity'  => 'integer',
            'seats_filled'        => 'integer',
            'rte_seats_filled'    => 'integer',
        ]);

        parent::__construct($data);
    }
}
