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
 * @property int|null $subject_category_id
 * @property int|null $is_language_subject
 * @property string|null $stream_applicability
 */
class Subject extends BaseEntity
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'subject_id'          => 'integer',
            'subject_category_id' => 'integer',
            'is_language_subject' => 'integer',
        ]);

        parent::__construct($data);
    }
}
