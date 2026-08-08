<?php

declare(strict_types=1);

namespace App\Modules\Administration\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\DTOs\CreateRoleRequest;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Entities\Role;
use App\Modules\Administration\Models\RoleModel;

/**
 * docs/design/administration/Phase-4-Service-Design.md
 * RBAC (ADR-024 §3): Administration is Tier-1-only, including reads —
 * every method here requires `administration.manage`.
 */
class RoleService
{
    public function __construct(
        private readonly RoleModel $roleModel,
        private readonly AuditService $auditService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    public function createRole(CreateRoleRequest $request): Role
    {
        $this->moduleAuthorizer->assertManage(UserService::PERMISSION_MANAGE);

        if ($this->roleModel->existsByRoleName($request->roleName)) {
            throw new BusinessRuleException('ROLE_NAME_ALREADY_TAKEN', 'This role name is already taken.');
        }

        $id = $this->roleModel->insert([
            'role_name'      => $request->roleName,
            'description'    => $request->description,
            'is_system_role' => false,
            'permission_set' => $request->permissionSet,
        ], true);

        $role = $this->roleModel->find($id);

        $this->auditService->record('Role', $id, AuditLog::ACTION_CREATE, null, $role->toRawArray());

        return $role;
    }

    public function updateRole(int $id, CreateRoleRequest $request): Role
    {
        $this->moduleAuthorizer->assertManage(UserService::PERMISSION_MANAGE);

        $before = $this->roleModel->find($id);

        if ($before === null) {
            throw new BusinessRuleException('ROLE_NOT_FOUND', 'Role not found.');
        }

        if ($this->roleModel->existsByRoleNameExceptId($request->roleName, $id)) {
            throw new BusinessRuleException('ROLE_NAME_ALREADY_TAKEN', 'This role name is already taken.');
        }

        $this->roleModel->update($id, [
            'role_name'      => $request->roleName,
            'description'    => $request->description,
            'permission_set' => $request->permissionSet,
        ]);

        $after = $this->roleModel->find($id);

        $this->auditService->record('Role', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return $after;
    }

    public function deleteRole(int $id): void
    {
        $this->moduleAuthorizer->assertManage(UserService::PERMISSION_MANAGE);

        $role = $this->roleModel->find($id);

        if ($role === null) {
            throw new BusinessRuleException('ROLE_NOT_FOUND', 'Role not found.');
        }

        if ($role->is_system_role) {
            throw new BusinessRuleException('ROLE_IS_SYSTEM_PROTECTED', 'System-default roles cannot be deleted.');
        }

        if ($this->roleModel->isReferencedByAnyUser($id)) {
            throw new BusinessRuleException(
                'ROLE_IN_USE',
                'This role is assigned to at least one user and cannot be deleted.',
            );
        }

        $this->roleModel->delete($id);

        $this->auditService->record('Role', $id, AuditLog::ACTION_DELETE, $role->toRawArray(), null);
    }

    public function getRole(int $id): Role
    {
        $this->moduleAuthorizer->assertManage(UserService::PERMISSION_MANAGE);

        $role = $this->roleModel->find($id);

        if ($role === null) {
            throw new BusinessRuleException('ROLE_NOT_FOUND', 'Role not found.');
        }

        return $role;
    }

    /**
     * @return list<Role>
     */
    public function listRoles(): array
    {
        $this->moduleAuthorizer->assertManage(UserService::PERMISSION_MANAGE);

        return $this->roleModel->findAll();
    }
}
