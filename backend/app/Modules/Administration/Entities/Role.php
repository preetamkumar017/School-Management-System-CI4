<?php

declare(strict_types=1);

namespace App\Modules\Administration\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/administration/Phase-1-Domain-Model.md — ENT-SYS-002.
 *
 * @property int|null    $role_id
 * @property string      $role_name
 * @property string|null $description
 * @property bool        $is_system_role
 * @property list<string> $permission_set
 */
class Role extends BaseEntity
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'role_id'        => 'integer',
            'is_system_role' => 'boolean',
            'permission_set' => 'json-array',
        ]);

        parent::__construct($data);
    }
}
