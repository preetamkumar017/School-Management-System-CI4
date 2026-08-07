<?php

declare(strict_types=1);

namespace App\Modules\Reports\DTOs;

/**
 * docs/design/reports/Phase-1-Service-Controller-Design.md
 * Not backed by any entity — Reports has none (ADR-010 §7).
 */
final class SummaryResponse
{
    public function __construct(
        public readonly string $generatedAt,
        public readonly int $totalUsers,
        public readonly int $totalClasses,
        public readonly int $totalAcademicSessions,
        public readonly int $totalDepartments,
        public readonly int $totalDesignations,
        public readonly int $totalEmployees,
        public readonly int $totalBooks,
        public readonly int $booksAvailable,
        public readonly int $totalVehicles,
        public readonly int $totalRoutes,
        public readonly int $totalFeeHeads,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'generated_at'             => $this->generatedAt,
            'total_users'              => $this->totalUsers,
            'total_classes'            => $this->totalClasses,
            'total_academic_sessions'  => $this->totalAcademicSessions,
            'total_departments'        => $this->totalDepartments,
            'total_designations'       => $this->totalDesignations,
            'total_employees'          => $this->totalEmployees,
            'total_books'              => $this->totalBooks,
            'books_available'          => $this->booksAvailable,
            'total_vehicles'           => $this->totalVehicles,
            'total_routes'             => $this->totalRoutes,
            'total_fee_heads'          => $this->totalFeeHeads,
        ];
    }
}
