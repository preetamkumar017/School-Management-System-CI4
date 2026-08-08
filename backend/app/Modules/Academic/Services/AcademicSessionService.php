<?php

declare(strict_types=1);

namespace App\Modules\Academic\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Academic\DTOs\AcademicSessionResponse;
use App\Modules\Academic\DTOs\AcademicSessionStatusChangeRequest;
use App\Modules\Academic\DTOs\CreateAcademicSessionRequest;
use App\Modules\Academic\DTOs\UpdateAcademicSessionRequest;
use App\Modules\Academic\Entities\AcademicSession;
use App\Modules\Academic\Models\AcademicSessionModel;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;

/**
 * docs/design/academic/Phase-4-Service-Design.md
 * RBAC (ADR-024 §3, Phase 2): `academic.manage` (Tier 1 only) gates writes.
 */
class AcademicSessionService
{
    public const PERMISSION_MANAGE = 'academic.manage';

    /**
     * Forward-only lifecycle (BR-SIS-001, Phase 1): PLANNED -> ACTIVE ->
     * CLOSED -> ARCHIVED. No document defines a reversal path.
     *
     * @var list<string>
     */
    private const STATUS_ORDER = [
        AcademicSession::STATUS_PLANNED,
        AcademicSession::STATUS_ACTIVE,
        AcademicSession::STATUS_CLOSED,
        AcademicSession::STATUS_ARCHIVED,
    ];

    public function __construct(
        private readonly AcademicSessionModel $academicSessionModel,
        private readonly AuditService $auditService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    public function createSession(CreateAcademicSessionRequest $request): AcademicSessionResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        if ($this->academicSessionModel->existsBySessionName($request->sessionName)) {
            throw new BusinessRuleException(
                'ACADEMIC_SESSION_NAME_ALREADY_TAKEN',
                'This academic session name is already taken.',
            );
        }

        $this->assertNoOverlap($request->startDate, $request->endDate);

        $id = $this->academicSessionModel->insert([
            'session_name' => $request->sessionName,
            'start_date'   => $request->startDate,
            'end_date'     => $request->endDate,
            'status'       => AcademicSession::STATUS_PLANNED,
        ], true);

        $session = $this->academicSessionModel->find($id);

        $this->auditService->record('AcademicSession', $id, AuditLog::ACTION_CREATE, null, $session->toRawArray());

        return new AcademicSessionResponse($session);
    }

    public function updateSession(int $id, UpdateAcademicSessionRequest $request): AcademicSessionResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireSession($id);

        if ($this->academicSessionModel->existsBySessionNameExceptId($request->sessionName, $id)) {
            throw new BusinessRuleException(
                'ACADEMIC_SESSION_NAME_ALREADY_TAKEN',
                'This academic session name is already taken.',
            );
        }

        $this->assertNoOverlap($request->startDate, $request->endDate, $id);

        $this->academicSessionModel->update($id, [
            'session_name' => $request->sessionName,
            'start_date'   => $request->startDate,
            'end_date'     => $request->endDate,
        ]);

        $after = $this->academicSessionModel->find($id);

        $this->auditService->record(
            'AcademicSession',
            $id,
            AuditLog::ACTION_UPDATE,
            $before->toRawArray(),
            $after->toRawArray(),
        );

        return new AcademicSessionResponse($after);
    }

    public function changeStatus(int $id, AcademicSessionStatusChangeRequest $request): AcademicSessionResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireSession($id);

        $fromIndex = array_search($before->status, self::STATUS_ORDER, true);
        $toIndex   = array_search($request->status, self::STATUS_ORDER, true);

        if ($toIndex === false || $fromIndex === false || $toIndex <= $fromIndex) {
            throw new BusinessRuleException(
                'ACADEMIC_SESSION_INVALID_STATUS_TRANSITION',
                "Cannot transition an academic session from {$before->status} to {$request->status}.",
            );
        }

        $this->academicSessionModel->update($id, ['status' => $request->status]);

        $after = $this->academicSessionModel->find($id);

        $this->auditService->record(
            'AcademicSession',
            $id,
            AuditLog::ACTION_UPDATE,
            $before->toRawArray(),
            $after->toRawArray(),
        );

        return new AcademicSessionResponse($after);
    }

    public function getSession(int $id): AcademicSessionResponse
    {
        return new AcademicSessionResponse($this->requireSession($id));
    }

    /**
     * @return list<AcademicSessionResponse>
     */
    public function listSessions(): array
    {
        return array_map(
            static fn (AcademicSession $session): AcademicSessionResponse => new AcademicSessionResponse($session),
            $this->academicSessionModel->findAll(),
        );
    }

    public function getCurrentActiveSession(): ?AcademicSessionResponse
    {
        $sessions = $this->academicSessionModel->findByStatus(AcademicSession::STATUS_ACTIVE);
        $session  = $sessions[0] ?? null;

        return $session === null ? null : new AcademicSessionResponse($session);
    }

    private function requireSession(int $id): AcademicSession
    {
        $session = $this->academicSessionModel->find($id);

        if ($session === null) {
            throw new BusinessRuleException('ACADEMIC_SESSION_NOT_FOUND', 'Academic session not found.');
        }

        return $session;
    }

    private function assertNoOverlap(string $startDate, string $endDate, ?int $exceptId = null): void
    {
        if ($this->academicSessionModel->findOverlapping($startDate, $endDate, $exceptId) !== []) {
            throw new BusinessRuleException(
                'ACADEMIC_SESSION_DATE_RANGE_OVERLAPS',
                'This date range overlaps an existing academic session.',
            );
        }
    }
}
