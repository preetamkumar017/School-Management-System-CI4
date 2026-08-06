<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use App\Core\BaseModel;
use App\Modules\Attendance\Entities\AttendanceRecord;

/**
 * docs/design/attendance/Phase-2-Model-DTO-Design.md
 */
class AttendanceRecordModel extends BaseModel
{
    protected $table          = 'attendance_records';
    protected $primaryKey     = 'attendance_record_id';
    protected $returnType     = AttendanceRecord::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'student_id',
        'timetable_entry_id',
        'attendance_date',
        'state',
        'marked_by',
        'is_locked',
        'created_by',
        'updated_by',
    ];

    public function existsByStudentEntryDate(int $studentId, int $timetableEntryId, string $date): bool
    {
        return $this->where('student_id', $studentId)
            ->where('timetable_entry_id', $timetableEntryId)
            ->where('attendance_date', $date)
            ->countAllResults() > 0;
    }

    public function findByStudentEntryDate(int $studentId, int $timetableEntryId, string $date): ?AttendanceRecord
    {
        return $this->where('student_id', $studentId)
            ->where('timetable_entry_id', $timetableEntryId)
            ->where('attendance_date', $date)
            ->first();
    }

    /**
     * @return list<AttendanceRecord>
     */
    public function findByStudentBetween(int $studentId, string $fromDate, string $toDate): array
    {
        return $this->where('student_id', $studentId)
            ->where('attendance_date >=', $fromDate)
            ->where('attendance_date <=', $toDate)
            ->findAll();
    }

    /**
     * @return list<AttendanceRecord>
     */
    public function findByTimetableEntryAndDate(int $timetableEntryId, string $date): array
    {
        return $this->where('timetable_entry_id', $timetableEntryId)->where('attendance_date', $date)->findAll();
    }
}
