<?php

declare(strict_types=1);

namespace App\Modules\Administration\Controllers;

use App\Core\BaseController;
use App\Modules\Administration\DTOs\CreateRoleRequest;
use Config\Services;

/**
 * docs/design/administration/Phase-5-Controller-Design.md
 * Base path /api/v1/administration/roles
 */
class RoleController extends BaseController
{
    public function create()
    {
        $role = Services::roleService()->createRole($this->fromBody());

        return $this->respondCreated($this->toResponse($role));
    }

    public function update(int $id)
    {
        $role = Services::roleService()->updateRole($id, $this->fromBody());

        return $this->respondSuccess($this->toResponse($role));
    }

    public function delete(int $id)
    {
        Services::roleService()->deleteRole($id);

        return $this->respondSuccess();
    }

    public function show(int $id)
    {
        return $this->respondSuccess($this->toResponse(Services::roleService()->getRole($id)));
    }

    public function index()
    {
        return $this->respondSuccess(array_map($this->toResponse(...), Services::roleService()->listRoles()));
    }

    private function fromBody(): CreateRoleRequest
    {
        $body = $this->request->getJSON(true) ?? [];

        return new CreateRoleRequest(
            roleName: (string) ($body['role_name'] ?? ''),
            description: isset($body['description']) ? (string) $body['description'] : null,
            permissionSet: (array) ($body['permission_set'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toResponse($role): array
    {
        return [
            'role_id'        => $role->role_id,
            'role_name'      => $role->role_name,
            'description'    => $role->description,
            'is_system_role' => $role->is_system_role,
            'permission_set' => $role->permission_set,
        ];
    }
}
