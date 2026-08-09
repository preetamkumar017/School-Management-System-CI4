<?php

declare(strict_types=1);

namespace App\Modules\Timetable\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/timetable/Phase-1-Domain-Model.md — ENT-TT-001.
 *
 * @property int|null    $timetable_entry_id
 * @property int         $section_id
 * @property int         $subject_id
 * @property int         $employee_id
 * @property string      $day_of_week
 * @property int         $period_no
 * @property string|null $room_id
 * @property int         $version_no
 * @property string      $status
 * @property bool        $is_extra_class
 */
class TimetableEntry extends BaseEntity
{
    public const DAY_MONDAY    = 'MONDAY';
    public const DAY_TUESDAY   = 'TUESDAY';
    public const DAY_WEDNESDAY = 'WEDNESDAY';
    public const DAY_THURSDAY  = 'THURSDAY';
    public const DAY_FRIDAY    = 'FRIDAY';
    public const DAY_SATURDAY  = 'SATURDAY';

    public const STATUS_DRAFT     = 'DRAFT';
    public const STATUS_PUBLISHED = 'PUBLISHED';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'timetable_entry_id' => 'integer',
            'section_id'         => 'integer',
            'subject_id'         => 'integer',
            'employee_id'        => 'integer',
            'period_no'          => 'integer',
            'version_no'         => 'integer',
            'is_extra_class'     => 'boolean',
        ]);

        parent::__construct($data);
    }
}
