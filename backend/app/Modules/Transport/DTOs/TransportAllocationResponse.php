<?php

declare(strict_types=1);

namespace App\Modules\Transport\DTOs;

use App\Modules\Transport\Entities\TransportAllocation;

/**
 * docs/design/transport/Phase-2-Model-DTO-Design.md
 */
final class TransportAllocationResponse
{
    public readonly int $transportAllocationId;
    public readonly int $studentId;
    public readonly int $routeId;
    public readonly string $stopName;
    public readonly string $emergencyContact;
    public readonly string $status;

    public function __construct(TransportAllocation $allocation)
    {
        $this->transportAllocationId = $allocation->transport_allocation_id;
        $this->studentId             = $allocation->student_id;
        $this->routeId               = $allocation->route_id;
        $this->stopName              = $allocation->stop_name;
        $this->emergencyContact      = $allocation->emergency_contact;
        $this->status                = $allocation->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'transport_allocation_id' => $this->transportAllocationId,
            'student_id'              => $this->studentId,
            'route_id'                => $this->routeId,
            'stop_name'               => $this->stopName,
            'emergency_contact'       => $this->emergencyContact,
            'status'                  => $this->status,
        ];
    }
}
