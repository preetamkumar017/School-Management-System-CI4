<?php

declare(strict_types=1);

namespace Tests\Support\Attendance;

use App\Modules\Attendance\Models\AttendanceRecordModel;
use Tests\Support\Timetable\TimetableTestCase;

/**
 * @internal
 */
abstract class AttendanceTestCase extends TimetableTestCase
{
    protected function createAttendanceRecordFixture(
        ?int $studentId = null,
        ?int $timetableEntryId = null,
        ?string $attendanceDate = null,
        string $state = 'PRESENT',
        bool $isLocked = false,
    ): int {
        return (new AttendanceRecordModel())->insert([
            'student_id'         => $studentId ?? $this->createStudentFixture(),
            'timetable_entry_id' => $timetableEntryId ?? $this->createTimetableEntryFixture(),
            'attendance_date'    => $attendanceDate ?? date('Y-m-d'),
            'state'              => $state,
            'marked_by'          => 1,
            'is_locked'          => $isLocked,
        ], true);
    }
}
