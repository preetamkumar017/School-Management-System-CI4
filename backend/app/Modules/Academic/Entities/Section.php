<?php

declare(strict_types=1);

namespace App\Modules\Academic\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/academic/Phase-1-Domain-Model.md — ENT-ACAD-003.
 *
 * @property int|null $section_id
 * @property int      $class_id
 * @property string   $section_name
 * @property int      $capacity
 */
class Section extends BaseEntity
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'section_id' => 'integer',
            'class_id'   => 'integer',
            'capacity'   => 'integer',
        ]);

        parent::__construct($data);
    }
}
