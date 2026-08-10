<?php

declare(strict_types=1);

namespace App\Modules\Administration\Entities;

use App\Core\BaseEntity;

/**
 * @property int|null $district_id
 * @property int      $state_id
 * @property string   $name
 */
class District extends BaseEntity
{
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'district_id' => 'integer',
            'state_id'    => 'integer',
        ]);
        parent::__construct($data);
    }
}
