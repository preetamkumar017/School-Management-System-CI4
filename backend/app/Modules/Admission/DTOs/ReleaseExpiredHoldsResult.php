<?php

declare(strict_types=1);

namespace App\Modules\Admission\DTOs;

/**
 * docs/ADR/ADR-016-admission-seat-hold-and-waitlist.md §4/§6 — the
 * response shape for the explicit-trigger
 * `POST /admission/applications/release-expired-holds` action.
 */
final class ReleaseExpiredHoldsResult
{
    /**
     * @param list<array{released_application_id: int, promoted_application_id: ?int}> $releases
     */
    public function __construct(
        public readonly array $releases,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'released_count'  => count($this->releases),
            'promoted_count'  => count(array_filter($this->releases, static fn (array $r): bool => $r['promoted_application_id'] !== null)),
            'releases'        => $this->releases,
        ];
    }
}
