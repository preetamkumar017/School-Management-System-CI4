<?php

declare(strict_types=1);

namespace App\Modules\Administration\Models;

use App\Core\BaseModel;
use App\Modules\Administration\Entities\Role;

/**
 * docs/design/administration/Phase-2-Model-Design.md
 */
class RoleModel extends BaseModel
{
    protected $table          = 'roles';
    protected $primaryKey     = 'role_id';
    protected $returnType     = Role::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'role_name',
        'description',
        'is_system_role',
        'permission_set',
        'created_by',
        'updated_by',
    ];

    protected $beforeInsert = ['stampCreatedBy', 'encodePermissionSet'];
    protected $beforeUpdate = ['stampUpdatedBy', 'encodePermissionSet'];

    /**
     * The Query Builder binds a raw PHP array as a tuple, not a JSON
     * value — callers pass permission_set as a plain array (Phase 3's
     * CreateRoleRequest), so it needs encoding here before it reaches the
     * builder, same as any other JSON column written from an array.
     */
    protected function encodePermissionSet(array $eventData): array
    {
        if (isset($eventData['data']['permission_set']) && is_array($eventData['data']['permission_set'])) {
            $eventData['data']['permission_set'] = json_encode($eventData['data']['permission_set']);
        }

        return $eventData;
    }

    public function findByRoleName(string $roleName): ?Role
    {
        return $this->where('role_name', $roleName)->first();
    }

    public function existsByRoleName(string $roleName): bool
    {
        return $this->where('role_name', $roleName)->countAllResults() > 0;
    }

    public function existsByRoleNameExceptId(string $roleName, int $id): bool
    {
        return $this->where('role_name', $roleName)->where('role_id !=', $id)->countAllResults() > 0;
    }

    public function isReferencedByAnyUser(int $roleId): bool
    {
        return $this->db->table('users')->where('role_id', $roleId)->countAllResults() > 0;
    }
}
