<?php

declare(strict_types=1);

namespace App\Modules\Administration\Entities;

use App\Core\BaseEntity;

/**
 * @property int|null $mapping_id
 * @property int      $framework_id
 * @property int      $academic_session_id
 */
class FrameworkSessionMapping extends BaseEntity
{
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'mapping_id'          => 'integer',
            'framework_id'        => 'integer',
            'academic_session_id' => 'integer',
        ]);
        parent::__construct($data);
    }
}
