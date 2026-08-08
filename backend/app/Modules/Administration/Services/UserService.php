<?php

declare(strict_types=1);

namespace App\Modules\Administration\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\DTOs\CreateUserRequest;
use App\Modules\Administration\DTOs\UpdateUserRequest;
use App\Modules\Administration\DTOs\UserStatusChangeRequest;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Entities\User;
use App\Modules\Administration\Models\UserModel;

/**
 * docs/design/administration/Phase-4-Service-Design.md
 * RBAC (ADR-024 §3): Administration is Tier-1-only, including reads —
 * every method here requires `administration.manage`.
 */
class UserService
{
    public const PERMISSION_MANAGE = 'administration.manage';

    public function __construct(
        private readonly UserModel $userModel,
        private readonly AuditService $auditService,
        private readonly AuthService $authService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    public function createUser(CreateUserRequest $request): User
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

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
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

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
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        return $this->changeStatusInternal($id, $request);
    }

    /**
     * Ungated variant of `changeStatus()` for internal, system-triggered
     * status changes that aren't a direct administrative action by the
     * caller — e.g. `EmployeeService::updateEmployee()`'s BR-HR-002
     * exit-date deactivation, which runs under the caller's own
     * `hr_payroll.manage` permission, not `administration.manage`. Never
     * call this from a Controller or any other module-external, directly
     * user-triggered path — that must go through `changeStatus()` so the
     * `administration.manage` gate applies (ADR-024 §3, this phase's
     * Addendum).
     */
    public function changeStatusInternal(int $id, UserStatusChangeRequest $request): User
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
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

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
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $query = $this->userModel;

        if ($status !== null) {
            $query = $query->where('status', $status);
        }

        return $query->findAll();
    }

    /**
     * Ungated existence check for other modules' internal Service-to-Service
     * calls (e.g. `CircularService::createCircular()`'s author_id check,
     * `NotificationLogService::validateRecipient()`'s User-recipient case)
     * — those callers are validating a foreign key, not performing a
     * user-facing Administration read, so `getUser()`'s RBAC gate doesn't
     * apply here (mirrors `EmployeeService::assertEmployeeExists()`).
     */
    public function assertUserExists(int $id): void
    {
        if ($this->userModel->find($id) === null) {
            throw new BusinessRuleException('USER_NOT_FOUND', 'User not found.');
        }
    }

    /**
     * Ungated count for Reports' `getSummary()` dashboard composition —
     * an aggregate count, not a per-record read, so `administration.manage`
     * isn't required (mirrors `EmployeeService::countEmployees()`).
     */
    public function countUsers(): int
    {
        return $this->userModel->countAllResults();
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
