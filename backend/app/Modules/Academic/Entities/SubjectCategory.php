<?php

declare(strict_types=1);

namespace App\Modules\Academic\Entities;

use App\Core\BaseEntity;

/**
 * @property int|null $subject_category_id
 * @property string   $category_name
 * @property string   $category_code
 * @property string|null $description
 * @property int      $is_active
 */
class SubjectCategory extends BaseEntity
{
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'subject_category_id' => 'integer',
            'is_active'           => 'integer',
        ]);

        parent::__construct($data);
    }
}
