<?php

declare(strict_types=1);

namespace App\Modules\Sis\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/sis/Phase-4.2-Domain-Model.md — ENT-SYS-003.
 *
 * @property int|null    $guardian_id
 * @property string      $full_name
 * @property string      $relationship
 * @property string      $mobile_number
 * @property string|null $email
 */
class Guardian extends BaseEntity
{
    public const RELATIONSHIP_FATHER   = 'FATHER';
    public const RELATIONSHIP_MOTHER   = 'MOTHER';
    public const RELATIONSHIP_GUARDIAN = 'GUARDIAN';
    public const RELATIONSHIP_OTHER    = 'OTHER';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'guardian_id' => 'integer',
        ]);

        parent::__construct($data);
    }
}
