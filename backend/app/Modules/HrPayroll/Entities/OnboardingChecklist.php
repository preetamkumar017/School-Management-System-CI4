<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Entities;

use CodeIgniter\Entity\Entity;

/**
 * @property int         $checklist_id
 * @property int         $employee_id
 * @property string      $item_name
 * @property bool        $is_done
 * @property string|null $done_at
 * @property string|null $done_by
 * @property string|null $remarks
 * @property int         $sort_order
 */
class OnboardingChecklist extends Entity
{
    protected $casts = [
        'checklist_id' => 'integer',
        'employee_id'  => 'integer',
        'is_done'      => 'boolean',
        'sort_order'   => 'integer',
    ];
}
