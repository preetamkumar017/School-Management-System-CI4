<?php

declare(strict_types=1);

namespace App\Modules\Transport\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/transport/Phase-1-Domain-Model.md — ENT-TRN-003.
 *
 * @property int|null $transport_allocation_id
 * @property int      $student_id
 * @property int      $route_id
 * @property string   $stop_name
 * @property string   $emergency_contact
 * @property string   $status
 */
class TransportAllocation extends BaseEntity
{
    public const STATUS_ACTIVE       = 'Active';
    public const STATUS_WAITLISTED   = 'Waitlisted';
    public const STATUS_DEALLOCATED  = 'De-allocated';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'transport_allocation_id' => 'integer',
            'student_id'              => 'integer',
            'route_id'                => 'integer',
        ]);

        parent::__construct($data);
    }
}
