<?php

declare(strict_types=1);

namespace App\Modules\Timetable\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/timetable/Phase-4-Substitution-Design.md — ENT-TT-003
 * (FR-16 / BR-TT-004). One row per absent-teacher/date/period — applies
 * "for that date only", never mutates TimetableEntry (ADR-013 §3).
 *
 * @property int|null $substitution_id
 * @property int      $timetable_entry_id
 * @property int      $absent_employee_id
 * @property int|null $substitute_employee_id
 * @property string   $substitution_date
 * @property string   $status
 */
class Substitution extends BaseEntity
{
    public const STATUS_ASSIGNED     = 'ASSIGNED';
    public const STATUS_UNSUPERVISED = 'UNSUPERVISED';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'substitution_id'        => 'integer',
            'timetable_entry_id'     => 'integer',
            'absent_employee_id'     => 'integer',
            'substitute_employee_id' => '?integer',
        ]);

        parent::__construct($data);
    }
}
