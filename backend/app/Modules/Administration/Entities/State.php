<?php

declare(strict_types=1);

namespace App\Modules\Administration\Entities;

use App\Core\BaseEntity;

/**
 * @property int|null $state_id
 * @property string   $name
 * @property string|null $code
 */
class State extends BaseEntity
{
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'state_id' => 'integer',
        ]);
        parent::__construct($data);
    }
}
