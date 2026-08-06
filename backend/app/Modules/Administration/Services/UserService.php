<?php

declare(strict_types=1);

namespace App\Modules\Administration\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\DTOs\CreateUserRequest;
use App\Modules\Administration\DTOs\UpdateUserRequest;
use App\Modules\Administration\DTOs\UserStatusChangeRequest;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Entities\User;
use App\Modules\Administration\Models\UserModel;

/**
 * docs/design/administration/Phase-4-Service-Design.md
 */
class UserService
{
    public function __construct(
        private readonly UserModel $userModel,
        private readonly AuditService $auditService,
        private readonly AuthService $authService,
    ) {
    }

    public function createUser(CreateUserRequest $request): User
    {
        if ($this->userModel->existsByUsername($request->username)) {
            throw new BusinessRuleException('USERNAME_ALREADY_TAKEN', 'This username is already taken.');
        }

        if ($this->userModel->findByOwner($request->ownerType, $request->ownerRefId) !== null) {
            throw new BusinessRuleException(
                'OWNER_ALREADY_HAS_USER',
                'This owner already has a login account.',
            );
        }

        $id = $this->userModel->insert([
            'username'      => $request->username,
            'password_hash' => password_hash($request->password, PASSWORD_BCRYPT),
            'role_id'       => $request->roleId,
            'owner_type'    => $request->ownerType,
            'owner_ref_id'  => $request->ownerRefId,
        ], true);

        $user = $this->userModel->find($id);

        $this->auditService->record('User', $id, AuditLog::ACTION_CREATE, null, $this->sanitizeForAudit($user));

        return $user;
    }

    public function updateUser(int $id, UpdateUserRequest $request): User
    {
        $before = $this->userModel->find($id);

        if ($before === null) {
            throw new BusinessRuleException('USER_NOT_FOUND', 'User not found.');
        }

        if ($this->userModel->existsByUsernameExceptId($request->username, $id)) {
            throw new BusinessRuleException('USERNAME_ALREADY_TAKEN', 'This username is already taken.');
        }

        $this->userModel->update($id, [
            'username' => $request->username,
            'role_id'  => $request->roleId,
        ]);

        $after = $this->userModel->find($id);

        $this->auditService->record(
            'User',
            $id,
            AuditLog::ACTION_UPDATE,
            $this->sanitizeForAudit($before),
            $this->sanitizeForAudit($after),
        );

        return $after;
    }

    public function changeStatus(int $id, UserStatusChangeRequest $request): User
    {
        $before = $this->userModel->find($id);

        if ($before === null) {
            throw new BusinessRuleException('USER_NOT_FOUND', 'User not found.');
        }

        $this->userModel->update($id, ['status' => $request->status]);

        if ($request->status === User::STATUS_DEACTIVATED) {
            $this->authService->logoutAll($id);
        }

        $after = $this->userModel->find($id);

        $this->auditService->record(
            'User',
            $id,
            AuditLog::ACTION_UPDATE,
            $this->sanitizeForAudit($before),
            $this->sanitizeForAudit($after),
        );

        return $after;
    }

    public function getUser(int $id): User
    {
        $user = $this->userModel->find($id);

        if ($user === null) {
            throw new BusinessRuleException('USER_NOT_FOUND', 'User not found.');
        }

        return $user;
    }

    /**
     * @return list<User>
     */
    public function listUsers(?string $status = null): array
    {
        $query = $this->userModel;

        if ($status !== null) {
            $query = $query->where('status', $status);
        }

        return $query->findAll();
    }

    /**
     * password_hash is Sensitive PII by the Company Development Standard's
     * classification — never written to the audit trail, same as it's
     * never returned in any API response.
     *
     * @return array<string, mixed>
     */
    private function sanitizeForAudit(User $user): array
    {
        $data = $user->toRawArray();
        unset($data['password_hash']);

        return $data;
    }
}
