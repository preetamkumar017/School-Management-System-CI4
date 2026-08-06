<?php

declare(strict_types=1);

namespace App\Modules\Administration\Controllers;

use App\Core\BaseController;
use App\Modules\Administration\DTOs\CreateUserRequest;
use App\Modules\Administration\DTOs\UpdateUserRequest;
use App\Modules\Administration\DTOs\UserStatusChangeRequest;
use Config\Services;

/**
 * docs/design/administration/Phase-5-Controller-Design.md
 * Base path /api/v1/administration/users — every route here requires a
 * valid access token (jwtauth filter, Config\Filters).
 */
class UserController extends BaseController
{
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

    public function changeStatus(int $id)
    {
        $body = $this->request->getJSON(true) ?? [];

        $request = new UserStatusChangeRequest(status: (string) ($body['status'] ?? ''));

        $user = Services::userService()->changeStatus($id, $request);

        return $this->respondSuccess($this->toResponse($user));
    }

    public function show(int $id)
    {
        return $this->respondSuccess($this->toResponse(Services::userService()->getUser($id)));
    }

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
