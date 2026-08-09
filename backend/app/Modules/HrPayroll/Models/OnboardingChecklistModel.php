<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Models;

use CodeIgniter\Model;
use App\Modules\HrPayroll\Entities\OnboardingChecklist;

class OnboardingChecklistModel extends Model
{
    protected $table          = 'employee_onboarding_checklists';
    protected $primaryKey     = 'checklist_id';
    protected $returnType     = OnboardingChecklist::class;
    protected $useTimestamps  = true;

    protected $allowedFields  = [
        'employee_id',
        'item_name',
        'is_done',
        'done_at',
        'done_by',
        'remarks',
        'sort_order',
    ];

    /** @return OnboardingChecklist[] */
    public function forEmployee(int $employeeId): array
    {
        return $this->where('employee_id', $employeeId)
                    ->orderBy('sort_order', 'ASC')
                    ->findAll();
    }

    public function countDone(int $employeeId): int
    {
        return $this->where('employee_id', $employeeId)
                    ->where('is_done', 1)
                    ->countAllResults();
    }

    public function countTotal(int $employeeId): int
    {
        return $this->where('employee_id', $employeeId)->countAllResults();
    }
}
