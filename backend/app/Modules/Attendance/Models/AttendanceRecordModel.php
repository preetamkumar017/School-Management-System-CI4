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

    /**
     * docs/ADR/ADR-022-reports-dashboard.md — Attendance overview (report
     * area 2): school-wide present/total marked-record counts for a date
     * range. PRESENT and LATE both count as "present" — the identical
     * definition AttendanceService::calculateAttendancePercentage already
     * uses per-student (FR-13), applied here school-wide.
     *
     * @return array{present: int, total: int}
     */
    public function countStatesForRange(string $fromDate, string $toDate): array
    {
        $row = $this->db->query(
            "SELECT COUNT(*) AS total, SUM(CASE WHEN state IN ('PRESENT', 'LATE') THEN 1 ELSE 0 END) AS present "
                . 'FROM attendance_records '
                . 'WHERE attendance_date >= ? AND attendance_date <= ? AND deleted_at IS NULL',
            [$fromDate, $toDate],
        )->getRowArray();

        return ['present' => (int) ($row['present'] ?? 0), 'total' => (int) ($row['total'] ?? 0)];
    }

    /**
     * Same present/total counts, grouped by the class each student
     * currently belongs to (Student.section_id -> Section.class_id).
     *
     * @return array<int, array{present: int, total: int}> class_id => counts
     */
    public function countStatesForRangeGroupedByClass(string $fromDate, string $toDate): array
    {
        $rows = $this->db->query(
            "SELECT sec.class_id AS class_id, COUNT(*) AS total, "
                . "SUM(CASE WHEN ar.state IN ('PRESENT', 'LATE') THEN 1 ELSE 0 END) AS present "
                . 'FROM attendance_records ar '
                . 'JOIN students st ON st.student_id = ar.student_id AND st.deleted_at IS NULL '
                . 'JOIN sections sec ON sec.section_id = st.section_id AND sec.deleted_at IS NULL '
                . 'WHERE ar.attendance_date >= ? AND ar.attendance_date <= ? AND ar.deleted_at IS NULL '
                . 'GROUP BY sec.class_id',
            [$fromDate, $toDate],
        )->getResultArray();

        $result = [];

        foreach ($rows as $row) {
            $result[(int) $row['class_id']] = ['present' => (int) $row['present'], 'total' => (int) $row['total']];
        }

        return $result;
    }

    /**
     * Same present/total counts, grouped by student — the input the
     * Reports "below the configured exam-eligibility threshold" list
     * (report area 2) filters against, reusing
     * attendance.exam_eligibility_min_percentage exactly as
     * AttendanceService::calculateAttendancePercentage already does.
     *
     * @return array<int, array{present: int, total: int}> student_id => counts
     */
    public function countStatesForRangeGroupedByStudent(string $fromDate, string $toDate): array
    {
        $rows = $this->db->query(
            "SELECT student_id, COUNT(*) AS total, "
                . "SUM(CASE WHEN state IN ('PRESENT', 'LATE') THEN 1 ELSE 0 END) AS present "
                . 'FROM attendance_records '
                . 'WHERE attendance_date >= ? AND attendance_date <= ? AND deleted_at IS NULL '
                . 'GROUP BY student_id',
            [$fromDate, $toDate],
        )->getResultArray();

        $result = [];

        foreach ($rows as $row) {
            $result[(int) $row['student_id']] = ['present' => (int) $row['present'], 'total' => (int) $row['total']];
        }

        return $result;
    }
}
