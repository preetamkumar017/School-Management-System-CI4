<?php

declare(strict_types=1);

namespace App\Modules\Administration\Controllers;

use App\Core\BaseController;
use App\Modules\Administration\DTOs\CreateUserRequest;
use App\Modules\Administration\DTOs\UpdateUserRequest;
use App\Modules\Administration\DTOs\UserStatusChangeRequest;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/administration/Phase-5-Controller-Design.md
 * Base path /api/v1/administration/users — every route here requires a
 * valid access token (jwtauth filter, Config\Filters).
 */
#[OA\Tag(name: 'Users')]
class UserController extends BaseController
{
    #[OA\Post(
        path: '/administration/users',
        tags: ['Users'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'password', 'role_id', 'owner_type', 'owner_ref_id'],
                properties: [
                    new OA\Property(property: 'username', type: 'string'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(property: 'role_id', type: 'integer'),
                    new OA\Property(property: 'owner_type', type: 'string', enum: ['EMPLOYEE', 'STUDENT', 'GUARDIAN']),
                    new OA\Property(property: 'owner_ref_id', type: 'integer'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/UserResponse')),
            new OA\Response(
                response: 422,
                description: 'USERNAME_ALREADY_TAKEN or OWNER_ALREADY_HAS_USER.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
        ],
    )]
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];

        $request = new CreateUserRequest(
            username: (string) ($body['username'] ?? ''),
            password: (string) ($body['password'] ?? ''),
            roleId: (int) ($body['role_id'] ?? 0),
            ownerType: (string) ($body['owner_type'] ?? ''),
            ownerRefId: (int) ($body['owner_ref_id'] ?? 0),
        );

        $user = Services::userService()->createUser($request);

        return $this->respondCreated($this->toResponse($user));
    }

    #[OA\Patch(
        path: '/administration/users/{id}',
        tags: ['Users'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'role_id'],
                properties: [
                    new OA\Property(property: 'username', type: 'string'),
                    new OA\Property(property: 'role_id', type: 'integer'),
                ],
            ),
        ),
        responses: [new OA\Response(response: 200, description: 'Updated.', content: new OA\JsonContent(ref: '#/components/schemas/UserResponse'))],
    )]
    public function update(int $id)
    {
        $body = $this->request->getJSON(true) ?? [];

        $request = new UpdateUserRequest(
            username: (string) ($body['username'] ?? ''),
            roleId: (int) ($body['role_id'] ?? 0),
        );

        $user = Services::userService()->updateUser($id, $request);

        return $this->respondSuccess($this->toResponse($user));
    }

    #[OA\Post(
        path: '/administration/users/{id}/status',
        tags: ['Users'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [new OA\Property(property: 'status', type: 'string', enum: ['ACTIVE', 'LOCKED', 'DEACTIVATED'])],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Status changed. Transitioning to DEACTIVATED revokes every session for this user.',
                content: new OA\JsonContent(ref: '#/components/schemas/UserResponse'),
            ),
        ],
    )]
    public function changeStatus(int $id)
    {
        $body = $this->request->getJSON(true) ?? [];

        $request = new UserStatusChangeRequest(status: (string) ($body['status'] ?? ''));

        $user = Services::userService()->changeStatus($id, $request);

        return $this->respondSuccess($this->toResponse($user));
    }

    #[OA\Get(
        path: '/administration/users/{id}',
        tags: ['Users'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/UserResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess($this->toResponse(Services::userService()->getUser($id)));
    }

    #[OA\Get(
        path: '/administration/users',
        tags: ['Users'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['ACTIVE', 'LOCKED', 'DEACTIVATED']))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/UserResponse')),
            ),
        ],
    )]
    public function index()
    {
        $status = $this->request->getGet('status');
        $users  = Services::userService()->listUsers($status);

        return $this->respondSuccess(array_map($this->toResponse(...), $users));
    }

    /**
     * UserResponse per Phase 3 — never password_hash, under any
     * circumstance or caller role.
     *
     * @return array<string, mixed>
     */
    private function toResponse($user): array
    {
        return [
            'user_id'            => $user->user_id,
            'username'           => $user->username,
            'role_id'            => $user->role_id,
            'owner_type'         => $user->owner_type,
            'owner_ref_id'       => $user->owner_ref_id,
            'status'             => $user->status,
            'last_login_at'      => $user->last_login_at?->toDateTimeString(),
        ];
    }
}
