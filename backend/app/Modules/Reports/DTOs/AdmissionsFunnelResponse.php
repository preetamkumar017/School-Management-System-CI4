<?php

declare(strict_types=1);

namespace App\Modules\Reports\DTOs;

/**
 * docs/ADR/ADR-022-reports-dashboard.md — report area 3 (Admissions
 * funnel). Not backed by any entity — Reports has none (ADR-010 §7).
 */
final class AdmissionsFunnelResponse
{
    /**
     * @param array<string, int> $countsByStatus status => count
     * @param list<array{class_id: int, total_capacity: int, seats_filled: int, occupancy_percentage: float}> $seatOccupancyByClass
     */
    public function __construct(
        public readonly int $academicSessionId,
        public readonly array $countsByStatus,
        public readonly array $seatOccupancyByClass,
        public readonly string $generatedAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'academic_session_id'     => $this->academicSessionId,
            'counts_by_status'        => $this->countsByStatus,
            'seat_occupancy_by_class' => $this->seatOccupancyByClass,
            'generated_at'            => $this->generatedAt,
        ];
    }
}
