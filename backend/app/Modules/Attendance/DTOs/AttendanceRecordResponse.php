<?php

declare(strict_types=1);

namespace App\Modules\Attendance\DTOs;

use App\Modules\Attendance\Entities\AttendanceRecord;

/**
 * docs/design/attendance/Phase-2-Model-DTO-Design.md
 */
final class AttendanceRecordResponse
{
    public readonly int $attendanceRecordId;
    public readonly int $studentId;
    public readonly int $timetableEntryId;
    public readonly string $attendanceDate;
    public readonly string $state;
    public readonly int $markedBy;
    public readonly bool $isLocked;

    public function __construct(AttendanceRecord $record)
    {
        $this->attendanceRecordId = $record->attendance_record_id;
        $this->studentId          = $record->student_id;
        $this->timetableEntryId   = $record->timetable_entry_id;
        $this->attendanceDate     = (string) $record->attendance_date;
        $this->state              = $record->state;
        $this->markedBy           = $record->marked_by;
        $this->isLocked           = $record->is_locked;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'attendance_record_id' => $this->attendanceRecordId,
            'student_id'           => $this->studentId,
            'timetable_entry_id'   => $this->timetableEntryId,
            'attendance_date'      => $this->attendanceDate,
            'state'                => $this->state,
            'marked_by'            => $this->markedBy,
            'is_locked'            => $this->isLocked,
        ];
    }
}
