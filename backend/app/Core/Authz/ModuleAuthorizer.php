<?php

declare(strict_types=1);

namespace App\Core\Authz;

use App\Core\Exceptions\AuthorizationException;
use App\Core\Http\RequestContext;
use App\Modules\Administration\Models\UserModel;

/**
 * docs/ADR/ADR-024-systemwide-rbac-enforcement.md §3.
 *
 * The single shared authorizer every module's Service layer calls at its
 * mutation (and, for Administration, read) entry points — centralizes the
 * two-tier "module-manage permission, or ownership" check instead of each
 * module duplicating it. Mirrors ADR-015/018's established shape: Service
 * layer (not Controller), `RequestContext::permissionSet()`,
 * `AuthorizationException` on denial.
 */
class ModuleAuthorizer
{
    public function __construct(private readonly UserModel $userModel)
    {
    }

    /**
     * Tier 1 only — for entities with no ownership concept (e.g.
     * Administration's User/Role/AuditLog, or any module's master/reference
     * data writes). Throws if the caller's JWT permission_set doesn't carry
     * `$managePermission`.
     */
    public function assertManage(string $managePermission): void
    {
        if (in_array($managePermission, RequestContext::permissionSet(), true)) {
            return;
        }

        throw new AuthorizationException(
            'NOT_AUTHORIZED',
            "This action requires the \"{$managePermission}\" permission.",
        );
    }

    /**
     * Tier 1 (module-manage permission) OR Tier 2 (the caller's own
     * `User.owner_type`/`owner_ref_id` matches the target record's owner).
     * Throws if neither passes.
     */
    public function assertManageOrOwner(string $managePermission, string $ownerType, int $ownerRefId): void
    {
        if (in_array($managePermission, RequestContext::permissionSet(), true)) {
            return;
        }

        $callerUserId = RequestContext::userId();
        $caller       = $callerUserId !== null ? $this->userModel->find($callerUserId) : null;

        if ($caller !== null && $caller->owner_type === $ownerType && $caller->owner_ref_id === $ownerRefId) {
            return;
        }

        throw new AuthorizationException(
            'NOT_AUTHORIZED',
            "This action requires the \"{$managePermission}\" permission or ownership of this record.",
        );
    }
}
