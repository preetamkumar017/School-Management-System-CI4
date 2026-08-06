<?php

declare(strict_types=1);

namespace App\Modules\Academic\Entities;

use CodeIgniter\Entity\Entity;

/**
 * docs/design/academic/Phase-1-Domain-Model.md — junction `ClassSubjectMap`.
 * Extends CodeIgniter\Entity\Entity directly, not App\Core\BaseEntity —
 * no surrogate PK, no audit-column baseline (same choice as AuditLog).
 *
 * @property int $class_id
 * @property int $subject_id
 */
class ClassSubjectMap extends Entity
{
    protected $casts = [
        'class_id'   => 'integer',
        'subject_id' => 'integer',
    ];
}
