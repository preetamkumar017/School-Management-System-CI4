<?php

declare(strict_types=1);

namespace App\Modules\Academic\Entities;

use CodeIgniter\Entity\Entity;

/**
 * docs/design/academic/Phase-1-Domain-Model.md — junction `ClassSubjectMap`.
 *
 * @property int $academic_session_id
 * @property int $class_id
 * @property int $subject_id
 * @property int $is_mandatory
 */
class ClassSubjectMap extends Entity
{
    protected $casts = [
        'academic_session_id' => 'integer',
        'class_id'            => 'integer',
        'subject_id'          => 'integer',
        'is_mandatory'        => 'integer',
    ];
}
