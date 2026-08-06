<?php

declare(strict_types=1);

namespace App\Modules\Administration\Controllers;

use App\Core\BaseController;
use App\Modules\Administration\DTOs\CreateRoleRequest;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/administration/Phase-5-Controller-Design.md
 * Base path /api/v1/administration/roles
 */
#[OA\Tag(name: 'Roles')]
class RoleController extends BaseController
{
    #[OA\Post(
        path: '/administration/roles',
        tags: ['Roles'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RoleRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/RoleResponse')),
            new OA\Response(response: 422, description: 'ROLE_NAME_ALREADY_TAKEN.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $role = Services::roleService()->createRole($this->fromBody());

        return $this->respondCreated($this->toResponse($role));
    }

    #[OA\Patch(
        path: '/administration/roles/{id}',
        tags: ['Roles'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/RoleRequest')),
        responses: [new OA\Response(response: 200, description: 'Updated.', content: new OA\JsonContent(ref: '#/components/schemas/RoleResponse'))],
    )]
    public function update(int $id)
    {
        $role = Services::roleService()->updateRole($id, $this->fromBody());

        return $this->respondSuccess($this->toResponse($role));
    }

    #[OA\Delete(
        path: '/administration/roles/{id}',
        tags: ['Roles'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Deleted.'),
            new OA\Response(
                response: 422,
                description: 'ROLE_IS_SYSTEM_PROTECTED or ROLE_IN_USE.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
        ],
    )]
    public function delete(int $id)
    {
        Services::roleService()->deleteRole($id);

        return $this->respondSuccess();
    }

    #[OA\Get(
        path: '/administration/roles/{id}',
        tags: ['Roles'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/RoleResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess($this->toResponse(Services::roleService()->getRole($id)));
    }

    #[OA\Get(
        path: '/administration/roles',
        tags: ['Roles'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/RoleResponse')),
            ),
        ],
    )]
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
