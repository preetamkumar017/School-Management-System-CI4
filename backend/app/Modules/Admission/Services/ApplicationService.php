<?php

declare(strict_types=1);

namespace App\Modules\Admission\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Admission\DTOs\ApplicationRejectRequest;
use App\Modules\Admission\DTOs\ApplicationResponse;
use App\Modules\Admission\DTOs\ApplicationShortlistRequest;
use App\Modules\Admission\DTOs\ApplicationVerifyRequest;
use App\Modules\Admission\DTOs\ApplicationWaitlistRequest;
use App\Modules\Admission\DTOs\CreateApplicationRequest;
use App\Modules\Admission\Entities\Application;
use App\Modules\Admission\Models\ApplicationModel;
use CodeIgniter\I18n\Time;
use Config\Services as AppServices;

/**
 * docs/design/admission/Phase-4-Service-Design.md
 * Core CRUD only — the SUBMITTED/VERIFIED/... -> ADMITTED transition
 * (FR-02 Confirm Enrollment, docs/design/admission/Phase-6) is not an
 * operation of this Service. It depends on SIS's StudentService, which
 * doesn't exist until Stage 5, and Phase 4/5's own approved scope
 * explicitly excludes it — see this file's class docblock reference.
 */
class ApplicationService
{
    public function __construct(
        private readonly ApplicationModel $applicationModel,
        private readonly AuditService $auditService,
    ) {
    }

    public function createApplication(CreateApplicationRequest $request): ApplicationResponse
    {
        // Validates class_applied_id against Academic's ClassService —
        // throws CLASS_NOT_FOUND (BusinessRuleException) if it doesn't
        // exist, reused as-is rather than duplicated here.
        AppServices::classService()->getClass($request->classAppliedId);

        $id = $this->applicationModel->insert([
            'application_reference_no' => $this->generateReferenceNo(),
            'applicant_name'           => $request->applicantName,
            'dob'                      => $request->dob,
            'class_applied_id'         => $request->classAppliedId,
            'aadhaar_number'           => $request->aadhaarNumber,
            'category'                 => $request->category,
            'status'                   => Application::STATUS_SUBMITTED,
            'submitted_at'             => Time::now()->toDateTimeString(),
        ], true);

        $application = $this->applicationModel->find($id);

        $this->auditService->record('Application', $id, AuditLog::ACTION_CREATE, null, $application->toRawArray());

        return new ApplicationResponse($application);
    }

    public function verifyApplication(int $id, ApplicationVerifyRequest $request): ApplicationResponse
    {
        return $this->transition($id, [Application::STATUS_SUBMITTED], Application::STATUS_VERIFIED);
    }

    public function shortlistApplication(int $id, ApplicationShortlistRequest $request): ApplicationResponse
    {
        return $this->transition($id, [Application::STATUS_VERIFIED], Application::STATUS_SHORTLISTED);
    }

    public function waitlistApplication(int $id, ApplicationWaitlistRequest $request): ApplicationResponse
    {
        return $this->transition(
            $id,
            [Application::STATUS_VERIFIED, Application::STATUS_SHORTLISTED],
            Application::STATUS_WAITLISTED,
        );
    }

    public function rejectApplication(int $id, ApplicationRejectRequest $request): ApplicationResponse
    {
        $before = $this->requireApplication($id);

        if (in_array($before->status, [Application::STATUS_ADMITTED, Application::STATUS_REJECTED], true)) {
            throw new BusinessRuleException(
                'APPLICATION_INVALID_STATUS_TRANSITION',
                "Cannot transition an application from {$before->status} to REJECTED.",
            );
        }

        $this->applicationModel->update($id, [
            'status'      => Application::STATUS_REJECTED,
            'decided_at'  => Time::now()->toDateTimeString(),
        ]);

        $after = $this->applicationModel->find($id);

        $this->auditService->record(
            'Application',
            $id,
            AuditLog::ACTION_UPDATE,
            $before->toRawArray(),
            $after->toRawArray(),
        );

        return new ApplicationResponse($after);
    }

    public function getApplication(int $id): ApplicationResponse
    {
        return new ApplicationResponse($this->requireApplication($id));
    }

    /**
     * @return array{items: list<ApplicationResponse>, total: int}
     */
    public function listApplications(?string $status, ?int $classId, int $page, int $perPage): array
    {
        $rows = $this->applicationModel->paginateByFilters($status, $classId, $page, $perPage);

        return [
            'items' => array_map(
                static fn (Application $application): ApplicationResponse => new ApplicationResponse($application),
                $rows,
            ),
            'total' => $this->applicationModel->pager->getTotal('default'),
        ];
    }

    /**
     * @param list<string> $allowedFrom
     */
    private function transition(int $id, array $allowedFrom, string $to): ApplicationResponse
    {
        $before = $this->requireApplication($id);

        if (! in_array($before->status, $allowedFrom, true)) {
            throw new BusinessRuleException(
                'APPLICATION_INVALID_STATUS_TRANSITION',
                "Cannot transition an application from {$before->status} to {$to}.",
            );
        }

        $this->applicationModel->update($id, ['status' => $to]);

        $after = $this->applicationModel->find($id);

        $this->auditService->record(
            'Application',
            $id,
            AuditLog::ACTION_UPDATE,
            $before->toRawArray(),
            $after->toRawArray(),
        );

        return new ApplicationResponse($after);
    }

    private function requireApplication(int $id): Application
    {
        $application = $this->applicationModel->find($id);

        if ($application === null) {
            throw new BusinessRuleException('APPLICATION_NOT_FOUND', 'Application not found.');
        }

        return $application;
    }

    /**
     * No exact generation algorithm is specified beyond the example format
     * (APP-2026-10023) and "system-generated" (Phase 1) — this generates a
     * candidate and retries on collision, with the DB's own unique
     * constraint as the final defense-in-depth backstop (same reasoning
     * the archived test plan calls out for reference-number collisions).
     */
    private function generateReferenceNo(): string
    {
        $year = date('Y');

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = sprintf('APP-%s-%05d', $year, random_int(10000, 99999));

            if (! $this->applicationModel->existsByReferenceNo($candidate)) {
                return $candidate;
            }
        }

        throw new BusinessRuleException(
            'APPLICATION_REFERENCE_NO_GENERATION_FAILED',
            'Could not generate a unique application reference number.',
        );
    }
}
