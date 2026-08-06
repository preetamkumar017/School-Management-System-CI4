<?php

declare(strict_types=1);

namespace Tests\Feature\Sis;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Academic\Models\AcademicSessionModel;
use App\Modules\Admission\Models\ApplicationModel;
use App\Modules\Admission\Models\SeatAllocationModel;
use App\Modules\Sis\Models\StudentModel;
use Tests\Support\Sis\SisTestCase;

/**
 * @internal
 * docs/ADR/ADR-004-student-stub-creation-shape-and-section-id-timing.md §5
 * and the roadmap's Stage 5 M4 exit criterion — "the single most important
 * test in the whole system so far" per the roadmap's own framing: proves
 * Admission's Confirm Enrollment orchestration and SIS's stub creation
 * genuinely share one transaction, not just that the happy path works.
 */
final class ConfirmEnrollmentTest extends SisTestCase
{
    public function testConfirmEnrollmentAdmitsTheApplicationAndCreatesTheStudentStub(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        [$applicationId, $classId, $sessionId] = $this->setUpShortlistedApplication($headers);

        $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/admission/seat-allocations', [
            'class_id'            => $classId,
            'academic_session_id' => $sessionId,
            'total_capacity'      => 40,
            'rte_quota_capacity'  => 10,
        ])->assertStatus(201);

        $confirm = $this->withHeaders($headers)->post("api/v1/admission/applications/{$applicationId}/confirm-enrollment");
        $confirm->assertStatus(200);
        $body = $this->decode($confirm)['data'];

        $this->assertSame('ADMITTED', $body['status']);
        $this->assertArrayHasKey('student_id', $body);

        $studentId = $body['student_id'];

        $show = $this->withHeaders($headers)->get("api/v1/sis/students/{$studentId}");
        $show->assertStatus(200);
        $student = $this->decode($show)['data'];

        $this->assertSame('DRAFT', $student['status']);
        $this->assertSame($applicationId, $student['application_id']);
        $this->assertNull($student['section_id']);
        $this->assertNotEmpty($student['admission_number']);

        $seatAllocation = (new SeatAllocationModel())->findByClassAndSession($classId, $sessionId);
        $this->assertSame(1, $seatAllocation->seats_filled);
    }

    public function testConfirmEnrollmentRejectsAnApplicationNotYetShortlistedOrWaitlisted(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $applicationId = $this->createApplicationFixture(null, 'SUBMITTED');

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->post("api/v1/admission/applications/{$applicationId}/confirm-enrollment"),
            BusinessRuleException::class,
            'APPLICATION_INVALID_STATUS_TRANSITION',
            422,
        );
    }

    public function testConfirmEnrollmentRequiresASeatAllocation(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        [$applicationId] = $this->setUpShortlistedApplication($headers);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->post("api/v1/admission/applications/{$applicationId}/confirm-enrollment"),
            BusinessRuleException::class,
            'SEAT_ALLOCATION_NOT_FOUND',
            422,
        );
    }

    /**
     * The headline test: forces a SIS-side failure (a Student row already
     * exists for this application_id — createStudentStub's own BR-SIS-002
     * guard rejects it) and proves the whole Confirm Enrollment
     * transaction rolls back atomically — the seat-count increment and
     * the Application.status change must never persist when SIS fails.
     */
    public function testForcedSisFailureRollsBackSeatCountAndApplicationStatusAtomically(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        [$applicationId, $classId, $sessionId] = $this->setUpShortlistedApplication($headers);

        $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/admission/seat-allocations', [
            'class_id'            => $classId,
            'academic_session_id' => $sessionId,
            'total_capacity'      => 40,
            'rte_quota_capacity'  => 10,
        ])->assertStatus(201);

        // Force the SIS-side failure: a Student already exists for this
        // application_id, so createStudentStub's own guard rejects it.
        $this->createStudentFixture($applicationId);

        $seatAllocationBefore = (new SeatAllocationModel())->findByClassAndSession($classId, $sessionId);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->post("api/v1/admission/applications/{$applicationId}/confirm-enrollment"),
            BusinessRuleException::class,
            'STUDENT_ALREADY_EXISTS_FOR_APPLICATION',
            422,
        );

        // Application.status must NOT have moved to ADMITTED.
        $application = (new ApplicationModel())->find($applicationId);
        $this->assertSame('SHORTLISTED', $application->status);
        $this->assertNull($application->decided_at);

        // seats_filled must NOT have incremented — rolled back atomically
        // alongside the status change, exactly as ADR-004 §5 requires.
        $seatAllocationAfter = (new SeatAllocationModel())->findByClassAndSession($classId, $sessionId);
        $this->assertSame($seatAllocationBefore->seats_filled, $seatAllocationAfter->seats_filled);

        // Only the one pre-existing Student row exists — no second one
        // was left behind by a partially-applied write.
        $students = (new StudentModel())->where('application_id', $applicationId)->findAll();
        $this->assertCount(1, $students);
    }

    public function testConfirmEnrollmentRejectsWhenSeatCapacityIsAlreadyFull(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $classId   = $this->createClassFixture();
        $sessionId = $this->createAcademicSession();
        (new AcademicSessionModel())->update($sessionId, ['status' => 'ACTIVE']);

        $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/admission/seat-allocations', [
            'class_id'            => $classId,
            'academic_session_id' => $sessionId,
            'total_capacity'      => 1,
            'rte_quota_capacity'  => 0,
        ])->assertStatus(201);

        $firstApplicationId  = $this->createShortlistedApplicationFor($headers, $classId);
        $secondApplicationId = $this->createShortlistedApplicationFor($headers, $classId);

        $this->withHeaders($headers)->post("api/v1/admission/applications/{$firstApplicationId}/confirm-enrollment")
            ->assertStatus(200);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->post("api/v1/admission/applications/{$secondApplicationId}/confirm-enrollment"),
            BusinessRuleException::class,
            'SEAT_CAPACITY_CEILING_REACHED',
            422,
        );

        $seatAllocation = (new SeatAllocationModel())->findByClassAndSession($classId, $sessionId);
        $this->assertSame(1, $seatAllocation->seats_filled);
    }

    /**
     * @return array{0: int, 1: int, 2: int} application_id, class_id, academic_session_id
     */
    private function setUpShortlistedApplication(array $headers): array
    {
        $classId   = $this->createClassFixture();
        $sessionId = $this->createAcademicSession();
        (new AcademicSessionModel())->update($sessionId, ['status' => 'ACTIVE']);

        $applicationId = $this->createShortlistedApplicationFor($headers, $classId);

        return [$applicationId, $classId, $sessionId];
    }

    private function createShortlistedApplicationFor(array $headers, int $classId): int
    {
        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/admission/applications', [
            'applicant_name'   => 'Confirm Enrollment Candidate',
            'dob'              => '2016-01-01',
            'class_applied_id' => $classId,
            'category'         => 'GENERAL',
        ]);
        $applicationId = $this->decode($create)['data']['application_id'];

        $this->withHeaders($headers)->post("api/v1/admission/applications/{$applicationId}/verify")->assertStatus(200);
        $this->withHeaders($headers)->post("api/v1/admission/applications/{$applicationId}/shortlist")->assertStatus(200);

        return $applicationId;
    }
}
