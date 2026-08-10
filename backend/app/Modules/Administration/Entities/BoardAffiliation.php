<?php

declare(strict_types=1);

namespace App\Modules\Administration\Entities;

use App\Core\BaseEntity;

/**
 * @property int|null    $affiliation_id
 * @property int         $board_id
 * @property int         $academic_session_id
 * @property string      $affiliation_number
 * @property string|null $validity_start
 * @property string|null $validity_end
 * @property string      $status
 */
class BoardAffiliation extends BaseEntity
{
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'affiliation_id'      => 'integer',
            'board_id'            => 'integer',
            'academic_session_id' => 'integer',
        ]);
        parent::__construct($data);
    }
}
