<?php

declare(strict_types=1);

namespace App\Modules\Administration\Entities;

use App\Core\BaseEntity;

/**
 * @property int|null $block_id
 * @property int      $district_id
 * @property string   $name
 */
class Block extends BaseEntity
{
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'block_id'    => 'integer',
            'district_id' => 'integer',
        ]);
        parent::__construct($data);
    }
}
