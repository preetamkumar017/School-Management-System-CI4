<?php

declare(strict_types=1);

namespace App\Modules\Academic\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/academic/Phase-1-Domain-Model.md — ENT-ACAD-002 (`Class`).
 * Named `AcademicClass`, not `Class` — `class` is a reserved word in PHP.
 * Table, Model, Service, Controller and DTO names keep the doc's own
 * "Class" naming, since none of those are reserved words.
 *
 * @property int|null $class_id
 * @property string   $class_name
 * @property int      $sequence_order
 */
class AcademicClass extends BaseEntity
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'class_id'       => 'integer',
            'sequence_order' => 'integer',
        ]);

        parent::__construct($data);
    }
}
