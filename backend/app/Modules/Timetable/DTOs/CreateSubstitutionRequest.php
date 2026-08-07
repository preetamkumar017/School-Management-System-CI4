<?php

declare(strict_types=1);

namespace App\Modules\Timetable\DTOs;

final class CreateSubstitutionRequest
{
    public function __construct(
        public readonly int $timetableEntryId,
        public readonly string $substitutionDate,
        public readonly ?int $substituteEmployeeId,
    ) {
    }
}
