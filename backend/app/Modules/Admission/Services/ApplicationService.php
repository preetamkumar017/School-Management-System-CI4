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
use App\Modules\Admission\DTOs\ConfirmEnrollmentResult;
use App\Modules\Admission\DTOs\CreateApplicationRequest;
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
 */
class ApplicationService
{
    public function __construct(
        private readonly ApplicationModel $applicationModel,
        private readonly SeatAllocationModel $seatAllocationModel,
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
                'status'     => Application::STATUS_ADMITTED,
                'decided_at' => Time::now()->toDateTimeString(),
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
