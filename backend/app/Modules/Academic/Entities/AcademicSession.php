<?php

declare(strict_types=1);

namespace App\Modules\Academic\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/academic/Phase-1-Domain-Model.md — ENT-ACAD-001.
 *
 * @property int|null $academic_session_id
 * @property string   $session_name
 * @property string   $start_date
 * @property string   $end_date
 * @property string   $status
 */
class AcademicSession extends BaseEntity
{
    public const STATUS_PLANNED  = 'PLANNED';
    public const STATUS_ACTIVE   = 'ACTIVE';
    public const STATUS_CLOSED   = 'CLOSED';
    public const STATUS_ARCHIVED = 'ARCHIVED';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'academic_session_id' => 'integer',
        ]);

        parent::__construct($data);
    }
}
