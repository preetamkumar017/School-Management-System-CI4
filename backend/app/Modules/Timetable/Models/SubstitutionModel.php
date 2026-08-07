<?php

declare(strict_types=1);

namespace App\Modules\Timetable\Models;

use App\Core\BaseModel;
use App\Modules\Timetable\Entities\Substitution;

/**
 * docs/design/timetable/Phase-4-Substitution-Design.md
 */
class SubstitutionModel extends BaseModel
{
    protected $table          = 'substitutions';
    protected $primaryKey     = 'substitution_id';
    protected $returnType     = Substitution::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'timetable_entry_id',
        'absent_employee_id',
        'substitute_employee_id',
        'substitution_date',
        'status',
        'created_by',
        'updated_by',
    ];

    public function existsByEntryDate(int $timetableEntryId, string $substitutionDate): bool
    {
        return $this->where('timetable_entry_id', $timetableEntryId)
            ->where('substitution_date', $substitutionDate)
            ->countAllResults() > 0;
    }
}
