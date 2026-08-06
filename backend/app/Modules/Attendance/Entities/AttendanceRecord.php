<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/attendance/Phase-1-Domain-Model.md — ENT-ATT-001.
 *
 * @property int|null $attendance_record_id
 * @property int      $student_id
 * @property int      $timetable_entry_id
 * @property string   $attendance_date
 * @property string   $state
 * @property int      $marked_by
 * @property bool     $is_locked
 */
class AttendanceRecord extends BaseEntity
{
    public const STATE_PRESENT = 'PRESENT';
    public const STATE_ABSENT  = 'ABSENT';
    public const STATE_LATE    = 'LATE';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'attendance_record_id' => 'integer',
            'student_id'           => 'integer',
            'timetable_entry_id'   => 'integer',
            'marked_by'            => 'integer',
            'is_locked'            => 'boolean',
        ]);

        parent::__construct($data);
    }
}
