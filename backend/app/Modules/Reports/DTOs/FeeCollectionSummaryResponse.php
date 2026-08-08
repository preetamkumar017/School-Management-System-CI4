<?php

declare(strict_types=1);

namespace App\Modules\Reports\DTOs;

/**
 * docs/ADR/ADR-022-reports-dashboard.md — report area 1 (Fee collection
 * summary). Not backed by any entity — Reports has none (ADR-010 §7).
 */
final class FeeCollectionSummaryResponse
{
    /**
     * @param array<int, float> $collectedByClass  class_id => amount
     * @param array<int, float> $outstandingByClass class_id => amount
     */
    public function __construct(
        public readonly int $academicSessionId,
        public readonly float $totalCollected,
        public readonly float $totalOutstanding,
        public readonly array $collectedByClass,
        public readonly array $outstandingByClass,
        public readonly int $defaulterCount,
        public readonly string $generatedAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'academic_session_id' => $this->academicSessionId,
            'total_collected'     => $this->totalCollected,
            'total_outstanding'   => $this->totalOutstanding,
            'collected_by_class'  => $this->collectedByClass,
            'outstanding_by_class' => $this->outstandingByClass,
            'defaulter_count'     => $this->defaulterCount,
            'generated_at'        => $this->generatedAt,
        ];
    }
}
