<?php

declare(strict_types=1);

namespace App\Modules\Sis\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Sis\DTOs\CreateGuardianRequest;
use App\Modules\Sis\DTOs\GuardianResponse;
use App\Modules\Sis\DTOs\UpdateGuardianRequest;
use App\Modules\Sis\Entities\Guardian;
use App\Modules\Sis\Mappers\GuardianMapper;
use App\Modules\Sis\Models\GuardianModel;

/**
 * docs/design/sis/Phase-4.6-Service-Design.md — intra-module only (ADR-002).
 * RBAC (ADR-024 §3, Phase 2): `sis.manage` (Tier 1 only) gates writes — no
 * Guardian ever holds a login in this system (verified: no code path
 * creates a `User` with `owner_type = GUARDIAN`), so there is no Tier 2
 * ownership concept for Guardian itself.
 */
class GuardianService
{
    public const PERMISSION_MANAGE = 'sis.manage';

    public function __construct(
        private readonly GuardianModel $guardianModel,
        private readonly GuardianMapper $guardianMapper,
        private readonly AuditService $auditService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    public function createGuardian(CreateGuardianRequest $request): GuardianResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $entity = $this->guardianMapper->toEntity($request);
        $id     = $this->guardianModel->insert($entity, true);

        $guardian = $this->guardianModel->find($id);

        $this->auditService->record('Guardian', $id, AuditLog::ACTION_CREATE, null, $guardian->toRawArray());

        return $this->guardianMapper->toResponse($guardian);
    }

    public function updateGuardian(int $id, UpdateGuardianRequest $request): GuardianResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $guardian = $this->requireGuardian($id);
        $before   = $guardian->toRawArray();

        $this->guardianMapper->updateEntity($request, $guardian);
        $this->guardianModel->save($guardian);

        $after = $this->guardianModel->find($id);

        $this->auditService->record('Guardian', $id, AuditLog::ACTION_UPDATE, $before, $after->toRawArray());

        return $this->guardianMapper->toResponse($after);
    }

    public function getGuardian(int $id): GuardianResponse
    {
        return $this->guardianMapper->toResponse($this->requireGuardian($id));
    }

    private function requireGuardian(int $id): Guardian
    {
        $guardian = $this->guardianModel->find($id);

        if ($guardian === null) {
            throw new BusinessRuleException('GUARDIAN_NOT_FOUND', 'Guardian not found.');
        }

        return $guardian;
    }
}
