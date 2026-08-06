<?php

declare(strict_types=1);

namespace App\Modules\Academic\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/academic/Phase-1-Domain-Model.md — ENT-ACAD-004.
 *
 * @property int|null $subject_id
 * @property string   $subject_name
 * @property string   $subject_code
 */
class Subject extends BaseEntity
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'subject_id' => 'integer',
        ]);

        parent::__construct($data);
    }
}
