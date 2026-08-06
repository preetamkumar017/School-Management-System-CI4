<?php

declare(strict_types=1);

namespace App\Modules\Timetable\DTOs;

use App\Modules\Timetable\Entities\TimetableEntry;

/**
 * docs/design/timetable/Phase-2-Model-DTO-Design.md
 */
final class TimetableEntryResponse
{
    public readonly int $timetableEntryId;
    public readonly int $sectionId;
    public readonly int $subjectId;
    public readonly int $employeeId;
    public readonly string $dayOfWeek;
    public readonly int $periodNo;
    public readonly ?string $roomId;
    public readonly int $versionNo;
    public readonly string $status;

    public function __construct(TimetableEntry $entry)
    {
        $this->timetableEntryId = $entry->timetable_entry_id;
        $this->sectionId        = $entry->section_id;
        $this->subjectId        = $entry->subject_id;
        $this->employeeId       = $entry->employee_id;
        $this->dayOfWeek        = $entry->day_of_week;
        $this->periodNo         = $entry->period_no;
        $this->roomId           = $entry->room_id;
        $this->versionNo        = $entry->version_no;
        $this->status           = $entry->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'timetable_entry_id' => $this->timetableEntryId,
            'section_id'         => $this->sectionId,
            'subject_id'         => $this->subjectId,
            'employee_id'        => $this->employeeId,
            'day_of_week'        => $this->dayOfWeek,
            'period_no'          => $this->periodNo,
            'room_id'            => $this->roomId,
            'version_no'         => $this->versionNo,
            'status'             => $this->status,
        ];
    }
}
