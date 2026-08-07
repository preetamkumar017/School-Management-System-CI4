<?php

declare(strict_types=1);

namespace App\Modules\Timetable\DTOs;

use App\Modules\Timetable\Entities\Substitution;

/**
 * docs/design/timetable/Phase-4-Substitution-Design.md
 */
final class SubstitutionResponse
{
    public readonly int $substitutionId;
    public readonly int $timetableEntryId;
    public readonly int $absentEmployeeId;
    public readonly ?int $substituteEmployeeId;
    public readonly string $substitutionDate;
    public readonly string $status;

    public function __construct(Substitution $substitution)
    {
        $this->substitutionId        = $substitution->substitution_id;
        $this->timetableEntryId      = $substitution->timetable_entry_id;
        $this->absentEmployeeId      = $substitution->absent_employee_id;
        $this->substituteEmployeeId  = $substitution->substitute_employee_id;
        $this->substitutionDate      = $substitution->substitution_date;
        $this->status                = $substitution->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'substitution_id'         => $this->substitutionId,
            'timetable_entry_id'      => $this->timetableEntryId,
            'absent_employee_id'      => $this->absentEmployeeId,
            'substitute_employee_id'  => $this->substituteEmployeeId,
            'substitution_date'       => $this->substitutionDate,
            'status'                  => $this->status,
        ];
    }
}
