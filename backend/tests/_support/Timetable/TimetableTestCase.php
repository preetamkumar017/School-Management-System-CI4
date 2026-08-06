<?php

declare(strict_types=1);

namespace Tests\Support\Timetable;

use App\Modules\Timetable\Models\TimetableEntryModel;
use Tests\Support\Examination\ExaminationTestCase;

/**
 * @internal
 */
abstract class TimetableTestCase extends ExaminationTestCase
{
    protected function createTimetableEntryFixture(
        ?int $sectionId = null,
        ?int $subjectId = null,
        ?int $employeeId = null,
        string $dayOfWeek = 'MONDAY',
        ?int $periodNo = null,
        string $status = 'PUBLISHED',
        ?string $roomId = null,
    ): int {
        return (new TimetableEntryModel())->insert([
            'section_id'  => $sectionId ?? $this->createSection(),
            'subject_id'  => $subjectId ?? $this->createSubject(),
            'employee_id' => $employeeId ?? random_int(1000, 999999),
            'day_of_week' => $dayOfWeek,
            'period_no'   => $periodNo ?? random_int(1, 8),
            'room_id'     => $roomId,
            'version_no'  => 1,
            'status'      => $status,
        ], true);
    }
}
