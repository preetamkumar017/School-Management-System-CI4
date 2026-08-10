<?php

declare(strict_types=1);

namespace App\Modules\Academic\Entities;

use App\Core\BaseEntity;

/**
 * @property int|null $class_board_map_id
 * @property int      $academic_session_id
 * @property int      $class_id
 * @property int      $framework_id
 */
class ClassBoardFrameworkMap extends BaseEntity
{
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'class_board_map_id'  => 'integer',
            'academic_session_id' => 'integer',
            'class_id'            => 'integer',
            'framework_id'        => 'integer',
        ]);

        parent::__construct($data);
    }
}
