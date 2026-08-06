<?php

declare(strict_types=1);

namespace App\Modules\Sis\Entities;

use CodeIgniter\Entity\Entity;

/**
 * docs/design/sis/Phase-4.2-Domain-Model.md — junction `StudentGuardianLink`.
 * Extends CodeIgniter\Entity\Entity directly, not App\Core\BaseEntity —
 * no surrogate PK, no audit-column baseline (same choice as ClassSubjectMap).
 *
 * @property int  $student_id
 * @property int  $guardian_id
 * @property bool $is_primary_contact
 */
class StudentGuardianLink extends Entity
{
    protected $casts = [
        'student_id'         => 'integer',
        'guardian_id'        => 'integer',
        'is_primary_contact' => 'boolean',
    ];
}
