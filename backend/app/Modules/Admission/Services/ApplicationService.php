<?php

declare(strict_types=1);

namespace App\Modules\Admission\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Administration\Services\ConfigurationService;
use App\Modules\Admission\DTOs\ApplicationRejectRequest;
use App\Modules\Admission\DTOs\ApplicationResponse;
use App\Modules\Admission\DTOs\ApplicationShortlistRequest;
use App\Modules\Admission\DTOs\ApplicationVerifyRequest;
use App\Modules\Admission\DTOs\ApplicationWaitlistRequest;
use App\Modules\Admission\DTOs\ConfirmEnrollmentResult;
use App\Modules\Admission\DTOs\CreateApplicationRequest;
use App\Modules\Admission\DTOs\ReleaseExpiredHoldsResult;
use App\Modules\Admission\Entities\Application;
use App\Modules\Admission\Models\ApplicationModel;
use App\Modules\Admission\Models\SeatAllocationModel;
use CodeIgniter\I18n\Time;
use Config\Database;
use Config\Services as AppServices;
use Throwable;

/**
 * docs/design/admission/Phase-4-Service-Design.md (core CRUD) and
 * docs/design/admission/Phase-6-Service-Design-Confirm-Enrollment.md
 * (FR-02 Confirm Enrollment — the SUBMITTED/VERIFIED/... -> ADMITTED
 * transition, added once SIS's StudentService exists, per ADR-004).
 *
 * RBAC (ADR-024 §3, Phase 2): `admission.manage` (Tier 1 only) gates every
 * write — Application is read-only self-service-shaped (an applicant has
 * no login in this system), so no Tier 2. Reads stay open.
 */
class ApplicationService
{
    public const PERMISSION_MANAGE = 'admission.manage';

    /**
     * docs/ADR/ADR-016-admission-seat-hold-and-waitlist.md §2.
     */
    private const CONFIG_KEY_SEAT_HOLD_PERIOD_HOURS = 'admission.seat_hold_period_hours';

    public function __construct(
        private readonly ApplicationModel $applicationModel,
        private readonly SeatAllocationModel $seatAllocationModel,
        private readonly AuditService $auditService,
        private readonly ConfigurationService $configurationService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    public function createApplication(CreateApplicationRequest $request): ApplicationResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

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

    /**
     * docs/ADR/ADR-016-admission-seat-hold-and-waitlist.md §1: shortlisting
     * is the moment a seat offer's hold begins — `hold_expires_at` is set
     * here, not via a separate "place a hold" action.
     */
    public function shortlistApplication(int $id, ApplicationShortlistRequest $request): ApplicationResponse
    {
        return $this->transition(
            $id,
            [Application::STATUS_VERIFIED],
            Application::STATUS_SHORTLISTED,
            ['hold_expires_at' => $this->newHoldExpiry()],
        );
    }

    public function waitlistApplication(int $id, ApplicationWaitlistRequest $request): ApplicationResponse
    {
        return $this->transition(
            $id,
            [Application::STATUS_VERIFIED, Application::STATUS_SHORTLISTED],
            Application::STATUS_WAITLISTED,
            ['hold_expires_at' => null],
        );
    }

    public function rejectApplication(int $id, ApplicationRejectRequest $request): ApplicationResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireApplication($id);

        if (in_array($before->status, [Application::STATUS_ADMITTED, Application::STATUS_REJECTED], true)) {
            throw new BusinessRuleException(
                'APPLICATION_INVALID_STATUS_TRANSITION',
                "Cannot transition an application from {$before->status} to REJECTED.",
            );
        }

        $this->applicationModel->update($id, [
            'status'          => Application::STATUS_REJECTED,
            'decided_at'      => Time::now()->toDateTimeString(),
            'hold_expires_at' => null,
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

    /**
     * docs/ADR/ADR-016-admission-seat-hold-and-waitlist.md §4 — explicit
     * trigger only (no scheduler exists in this codebase). For every
     * `SHORTLISTED` application whose hold has lapsed: locks that
     * `Application` row, re-verifies eligibility under the lock (a
     * concurrent `confirmEnrollment()` may have already resolved it —
     * see §5's matching lock), releases it to `REJECTED`, then promotes
     * the earliest-`submitted_at` `WAITLISTED` application for the same
     * class (BR-ADM-008), if any, into the vacated offer — all inside one
     * transaction per candidate, closing the BR-ADM-001/BR-ADM-007 race
     * named in Appendix-C §3.2 Observation A.
     */
    public function releaseExpiredHolds(): ReleaseExpiredHoldsResult
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $now        = Time::now();
        $candidates = $this->applicationModel->findExpiredHolds($now->toDateTimeString());

        $releases = [];

        foreach ($candidates as $candidate) {
            $release = $this->releaseOneExpiredHold($candidate->application_id, $now);

            if ($release !== null) {
                $releases[] = $release;
            }
        }

        return new ReleaseExpiredHoldsResult($releases);
    }

    /**
     * @return array{released_application_id: int, promoted_application_id: ?int}|null
     *               null when the candidate was no longer eligible by the
     *               time its row lock was acquired (already resolved by a
     *               concurrent confirmEnrollment()).
     */
    private function releaseOneExpiredHold(int $applicationId, Time $now): ?array
    {
        $db = Database::connect();
        $db->transStart();

        try {
            $locked = $this->applicationModel->lockForUpdate($applicationId);

            if (
                $locked === null
                || $locked['status'] !== Application::STATUS_SHORTLISTED
                || $locked['hold_expires_at'] === null
                || $locked['hold_expires_at'] > $now->toDateTimeString()
            ) {
                $db->transComplete();

                return null;
            }

            $before = $this->applicationModel->find($applicationId);

            $this->applicationModel->update($applicationId, [
                'status'          => Application::STATUS_REJECTED,
                'decided_at'      => $now->toDateTimeString(),
                'hold_expires_at' => null,
            ]);

            $after = $this->applicationModel->find($applicationId);

            $this->auditService->record(
                'Application',
                $applicationId,
                AuditLog::ACTION_UPDATE,
                $before->toRawArray(),
                $after->toRawArray(),
                'BR-ADM-007: provisional seat hold expired without confirmation.',
            );

            $promotedId = $this->promoteEarliestWaitlisted((int) $locked['class_applied_id'], $now);
        } catch (Throwable $e) {
            $db->transRollback();

            throw $e;
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            throw new BusinessRuleException(
                'RELEASE_EXPIRED_HOLD_FAILED',
                "Could not release the expired hold for application {$applicationId}.",
            );
        }

        return ['released_application_id' => $applicationId, 'promoted_application_id' => $promotedId];
    }

    /**
     * BR-ADM-008: promotes the earliest-`submitted_at` `WAITLISTED`
     * application for the class, re-verified under its own row lock
     * (guards against two concurrent `releaseExpiredHolds()` calls both
     * promoting the same applicant). Must be called inside the same
     * transaction `releaseOneExpiredHold()` already owns.
     */
    private function promoteEarliestWaitlisted(int $classAppliedId, Time $now): ?int
    {
        $candidate = $this->applicationModel->findEarliestWaitlistedForClass($classAppliedId);

        if ($candidate === null) {
            return null;
        }

        $candidateId = (int) $candidate->application_id;

        $lockedCandidate = $this->applicationModel->lockForUpdate($candidateId);

        if ($lockedCandidate === null || $lockedCandidate['status'] !== Application::STATUS_WAITLISTED) {
            return null;
        }

        $before = $this->applicationModel->find($candidateId);

        $this->applicationModel->update($candidateId, [
            'status'          => Application::STATUS_SHORTLISTED,
            'hold_expires_at' => $this->newHoldExpiry($now),
        ]);

        $after = $this->applicationModel->find($candidateId);

        $this->auditService->record(
            'Application',
            $candidateId,
            AuditLog::ACTION_UPDATE,
            $before->toRawArray(),
            $after->toRawArray(),
            'BR-ADM-008: promoted from waitlist to fill a vacated seat hold, strict submitted_at order.',
        );

        return $candidateId;
    }

    private function newHoldExpiry(?Time $from = null): string
    {
        $hours = (int) $this->configurationService->getNumber(self::CONFIG_KEY_SEAT_HOLD_PERIOD_HOURS);

        return ($from ?? Time::now())->addHours($hours)->toDateTimeString();
    }

    /**
     * FR-02 Confirm Enrollment (Phase 6 Revision 2, ADR-004). Not a new
     * operation — the existing SHORTLISTED/WAITLISTED -> ADMITTED
     * transition, extended with Admission Number generation, seat/RTE
     * re-validation, and the SIS stub-creation call. Runs inside one
     * local transaction spanning both modules' work (ADR-004 §5): if
     * StudentService::createStudentStub throws for any reason, the seat
     * count increment and the status transition both roll back.
     */
    public function confirmEnrollment(int $id): ConfirmEnrollmentResult
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireApplication($id);

        if (! in_array($before->status, [Application::STATUS_SHORTLISTED, Application::STATUS_WAITLISTED], true)) {
            throw new BusinessRuleException(
                'APPLICATION_INVALID_STATUS_TRANSITION',
                "Cannot confirm enrollment for an application in status {$before->status}.",
            );
        }

        $currentSession = AppServices::academicSessionService()->getCurrentActiveSession();

        if ($currentSession === null) {
            throw new BusinessRuleException(
                'NO_ACTIVE_ACADEMIC_SESSION',
                'No academic session is currently ACTIVE.',
            );
        }

        $seatAllocation = $this->seatAllocationModel->findByClassAndSession(
            $before->class_applied_id,
            $currentSession->academicSessionId,
        );

        if ($seatAllocation === null) {
            throw new BusinessRuleException(
                'SEAT_ALLOCATION_NOT_FOUND',
                'No seat allocation exists for this class and the current academic session.',
            );
        }

        if (
            $before->aadhaar_number !== null
            && $this->applicationModel->existsByAadhaarNumberAmongAdmittedExceptId($before->aadhaar_number, $id)
        ) {
            throw new BusinessRuleException(
                'DUPLICATE_APPLICANT_IDENTITY',
                'An admitted application already exists for this Aadhaar number.',
            );
        }

        $isRte = $before->category === Application::CATEGORY_RTE;

        $db = Database::connect();
        $db->transStart();

        try {
            // docs/ADR/ADR-016-admission-seat-hold-and-waitlist.md §5:
            // re-verify the application's status under a row lock on the
            // same row releaseExpiredHolds() locks — closes the
            // BR-ADM-001/BR-ADM-007 race named in Appendix-C §3.2
            // Observation A. A stale pre-lock read (the plain find()
            // above, used for the seat/session/duplicate-identity
            // pre-checks) is not sufficient on its own.
            $locked = $this->applicationModel->lockForUpdate($id);

            if (
                $locked === null
                || ! in_array($locked['status'], [Application::STATUS_SHORTLISTED, Application::STATUS_WAITLISTED], true)
            ) {
                throw new BusinessRuleException(
                    'APPLICATION_INVALID_STATUS_TRANSITION',
                    'Cannot confirm enrollment: the application status changed before this request could be processed.',
                );
            }

            $incremented = $this->seatAllocationModel->incrementSeatsFilled($seatAllocation->seat_allocation_id, $isRte);

            if (! $incremented) {
                throw new BusinessRuleException(
                    $isRte ? 'RTE_QUOTA_CEILING_REACHED' : 'SEAT_CAPACITY_CEILING_REACHED',
                    $isRte
                        ? 'The RTE quota for this class/session is already full.'
                        : 'No open seats remain for this class/session.',
                );
            }

            $admissionNumber = $this->generateAdmissionNumber();

            $studentData = AppServices::studentService()->createStudentStub([
                'application_id'   => $id,
                'admission_number' => $admissionNumber,
                'full_name'        => $before->applicant_name,
                'dob'              => (string) $before->dob,
                'category'         => $before->category,
                'aadhaar_number'   => $before->aadhaar_number,
            ]);

            $this->applicationModel->update($id, [
                'status'          => Application::STATUS_ADMITTED,
                'decided_at'      => Time::now()->toDateTimeString(),
                'hold_expires_at' => null,
            ]);

            $after = $this->applicationModel->find($id);

            $this->auditService->record(
                'Application',
                $id,
                AuditLog::ACTION_APPROVE,
                $before->toRawArray(),
                $after->toRawArray(),
            );
        } catch (Throwable $e) {
            $db->transRollback();

            throw $e;
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            throw new BusinessRuleException(
                'CONFIRM_ENROLLMENT_FAILED',
                'Confirm Enrollment could not be completed.',
            );
        }

        return new ConfirmEnrollmentResult(new ApplicationResponse($after), $studentData['student_id']);
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
    /**
     * @param list<string>         $allowedFrom
     * @param array<string, mixed> $extra       additional fields to write alongside `status`
     *                                          (e.g. `hold_expires_at`, ADR-016 §1)
     */
    private function transition(int $id, array $allowedFrom, string $to, array $extra = []): ApplicationResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireApplication($id);

        if (! in_array($before->status, $allowedFrom, true)) {
            throw new BusinessRuleException(
                'APPLICATION_INVALID_STATUS_TRANSITION',
                "Cannot transition an application from {$before->status} to {$to}.",
            );
        }

        $this->applicationModel->update($id, ['status' => $to] + $extra);

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

    /**
     * docs/ADR/ADR-022-reports-dashboard.md — Admissions funnel (report
     * area 3): Reports composes this instead of touching ApplicationModel
     * directly (ADR-010 §7).
     *
     * @param list<int> $classIds
     *
     * @return array<string, int> status => count
     */
    public function getStatusCountsForClassIds(array $classIds): array
    {
        return $this->applicationModel->countGroupedByStatusForClassIds($classIds);
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

    /**
     * Admission Number generation (FR-02 §10 step 4) — same
     * candidate-with-retry approach as generateReferenceNo, without a
     * pre-check against SIS's students table: Admission has no
     * legitimate cross-module visibility into that table (the
     * cross-module rule), so StudentService::createStudentStub's own
     * BR-SIS-002 uniqueness check is the authoritative backstop here —
     * a collision throws from inside the shared transaction and rolls
     * everything back, same as any other Confirm Enrollment failure.
     */
    private function generateAdmissionNumber(): string
    {
        return sprintf('ADM-%s-%05d', date('Y'), random_int(10000, 99999));
    }
}
