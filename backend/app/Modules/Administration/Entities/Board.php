<?php

declare(strict_types=1);

namespace App\Modules\Administration\Entities;

use App\Core\BaseEntity;

/**
 * @property int|null    $board_id
 * @property string      $name
 * @property string      $short_name
 * @property string      $board_type
 * @property string      $country
 * @property string|null $state_applicability
 * @property string      $status
 * @property string|null $description
 */
class Board extends BaseEntity
{
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'board_id' => 'integer',
        ]);
        parent::__construct($data);
    }
}
