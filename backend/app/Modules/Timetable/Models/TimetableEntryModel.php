<?php

declare(strict_types=1);

namespace App\Modules\Timetable\Models;

use App\Core\BaseModel;
use App\Modules\Timetable\Entities\TimetableEntry;

/**
 * docs/design/timetable/Phase-2-Model-DTO-Design.md
 */
class TimetableEntryModel extends BaseModel
{
    protected $table          = 'timetable_entries';
    protected $primaryKey     = 'timetable_entry_id';
    protected $returnType     = TimetableEntry::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'section_id',
        'subject_id',
        'employee_id',
        'day_of_week',
        'period_no',
        'room_id',
        'version_no',
        'status',
        'is_extra_class',
        'created_by',
        'updated_by',
    ];

    public function existsByTeacherDayPeriod(int $employeeId, string $dayOfWeek, int $periodNo): bool
    {
        return $this->where('employee_id', $employeeId)
            ->where('day_of_week', $dayOfWeek)
            ->where('period_no', $periodNo)
            ->countAllResults() > 0;
    }

    public function existsByTeacherDayPeriodExceptId(int $employeeId, string $dayOfWeek, int $periodNo, int $id): bool
    {
        return $this->where('employee_id', $employeeId)
            ->where('day_of_week', $dayOfWeek)
            ->where('period_no', $periodNo)
            ->where('timetable_entry_id !=', $id)
            ->countAllResults() > 0;
    }

    public function existsBySectionDayPeriod(int $sectionId, string $dayOfWeek, int $periodNo): bool
    {
        return $this->where('section_id', $sectionId)
            ->where('day_of_week', $dayOfWeek)
            ->where('period_no', $periodNo)
            ->countAllResults() > 0;
    }

    public function existsBySectionDayPeriodExceptId(int $sectionId, string $dayOfWeek, int $periodNo, int $id): bool
    {
        return $this->where('section_id', $sectionId)
            ->where('day_of_week', $dayOfWeek)
            ->where('period_no', $periodNo)
            ->where('timetable_entry_id !=', $id)
            ->countAllResults() > 0;
    }

    public function existsByRoomDayPeriod(string $roomId, string $dayOfWeek, int $periodNo): bool
    {
        return $this->where('room_id', $roomId)
            ->where('day_of_week', $dayOfWeek)
            ->where('period_no', $periodNo)
            ->countAllResults() > 0;
    }

    public function existsByRoomDayPeriodExceptId(string $roomId, string $dayOfWeek, int $periodNo, int $id): bool
    {
        return $this->where('room_id', $roomId)
            ->where('day_of_week', $dayOfWeek)
            ->where('period_no', $periodNo)
            ->where('timetable_entry_id !=', $id)
            ->countAllResults() > 0;
    }

    public function countByEmployeeId(int $employeeId): int
    {
        return $this->where('employee_id', $employeeId)->countAllResults();
    }

    public function countByEmployeeIdExceptId(int $employeeId, int $id): int
    {
        return $this->where('employee_id', $employeeId)->where('timetable_entry_id !=', $id)->countAllResults();
    }

    /**
     * @return list<TimetableEntry>
     */
    public function findBySectionId(int $sectionId): array
    {
        return $this->where('section_id', $sectionId)->findAll();
    }
}
