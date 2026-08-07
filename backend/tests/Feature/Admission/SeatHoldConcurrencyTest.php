<?php

declare(strict_types=1);

namespace Tests\Feature\Admission;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Http\RequestContext;
use App\Modules\Administration\Models\RoleModel;
use App\Modules\Administration\Models\UserModel;
use App\Modules\Admission\Models\ApplicationModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use Config\Services;
use Throwable;

/**
 * @internal
 * docs/ADR/ADR-016-admission-seat-hold-and-waitlist.md §4/§5 — proves the
 * Appendix-C §3.2 Observation A race (BR-ADM-007's automatic hold release
 * vs. BR-ADM-001's seat-ceiling-guarding confirmEnrollment()) genuinely
 * cannot double-allocate, using two independent database connections, the
 * same style Stage 4's SeatAllocationConcurrencyTest established.
 *
 * Deliberately does NOT use AdmissionTestCase/DatabaseTestTrait — that
 * trait wraps each test in one shared transaction that gets rolled back,
 * which would hide the row-visibility-across-connections behavior this
 * test needs two genuinely independent connections to exercise.
 */
final class SeatHoldConcurrencyTest extends CIUnitTestCase
{
    /** @var list<int> */
    private array $applicationIds = [];

    private ?int $userId = null;
    private ?int $roleId = null;

    protected function setUp(): void
    {
        parent::setUp();

        // ApplicationService::releaseExpiredHolds()/confirmEnrollment() go
        // through AuditService, whose performed_by column is a real FK to
        // users.user_id — normally populated by JwtAuthFilter from an
        // authenticated request. This test calls the Service directly (no
        // HTTP layer, deliberately — see the class docblock), so it must
        // stand in for that filter with a real User row of its own.
        $this->roleId = (new RoleModel())->insert([
            'role_name'      => 'Role ' . uniqid('', true),
            'is_system_role' => false,
            'permission_set' => ['read', 'create', 'update', 'delete'],
        ], true);

        $this->userId = (new UserModel())->insert([
            'username'      => 'shconc_' . uniqid('', true),
            'password_hash' => password_hash('irrelevant', PASSWORD_BCRYPT),
            'role_id'       => $this->roleId,
            'owner_type'    => 'EMPLOYEE',
            'owner_ref_id'  => 1,
            'status'        => 'ACTIVE',
        ], true);

        RequestContext::setUserId($this->userId);
    }

    protected function tearDown(): void
    {
        RequestContext::reset();

        if ($this->applicationIds !== []) {
            Database::connect('tests')
                ->table('applications')
                ->whereIn('application_id', $this->applicationIds)
                ->delete();
        }

        // Soft-delete only (not purge): audit_logs rows created during the
        // test reference this user via a real FK
        // (fk_audit_logs_users, ON DELETE RESTRICT), so a hard delete
        // would fail here — matches this codebase's append-only audit
        // history elsewhere.
        if ($this->userId !== null) {
            (new UserModel())->delete($this->userId);
        }

        if ($this->roleId !== null) {
            (new RoleModel())->delete($this->roleId);
        }

        parent::tearDown();
    }

    /**
     * Connection A holds the exact row lock `releaseOneExpiredHold()`
     * takes (via `ApplicationModel::lockForUpdate()`) mid-transaction,
     * without committing — simulating `releaseExpiredHolds()` in flight.
     * Connection B then attempts the exact lock query
     * `confirmEnrollment()` issues for the same row and must block, proving
     * this is a real database row lock (not an application-level guard a
     * race could slip past). Connection A then completes the release it
     * was holding the lock for and commits; only then does the real
     * `ApplicationService::confirmEnrollment()` run for the same
     * application, and it must be rejected — the release already won, so
     * the seat is never double-allocated.
     */
    public function testRowLockPreventsConfirmEnrollmentFromSucceedingAfterAConcurrentHoldRelease(): void
    {
        $db      = Database::connect('tests');
        $now     = Time::now();
        $classId = 999301;

        $applicationId = $this->insertShortlistedApplicationWithExpiredHold($db, $classId, $now);
        $this->applicationIds[] = $applicationId;

        $connA = Database::connect('tests', false);
        $connB = Database::connect('tests', false);

        // Connection A: the exact lock releaseOneExpiredHold() takes,
        // held open (not yet committed).
        $connA->transBegin();
        $connA->query(
            'SELECT application_id, status, hold_expires_at, class_applied_id '
                . 'FROM applications WHERE application_id = ? FOR UPDATE',
            [$applicationId],
        );

        // Connection B: the exact lock confirmEnrollment() takes for the
        // same row, with a short lock-wait timeout so a genuine block is
        // observable within the test instead of hanging.
        $connB->query('SET SESSION innodb_lock_wait_timeout = 1');

        $blocked = false;

        try {
            $connB->query(
                'SELECT application_id, status, hold_expires_at, class_applied_id '
                    . 'FROM applications WHERE application_id = ? FOR UPDATE',
                [$applicationId],
            );
        } catch (Throwable $e) {
            $blocked = true;
        }

        $this->assertTrue(
            $blocked,
            "Connection B's confirmEnrollment()-style lock attempt should have blocked on A's in-flight release, but did not.",
        );

        // Connection A completes the release it was holding the lock for
        // (mirrors releaseOneExpiredHold()'s own writes) and commits.
        $connA->query(
            'UPDATE applications SET status = ?, hold_expires_at = NULL, decided_at = ? WHERE application_id = ?',
            ['REJECTED', $now->toDateTimeString(), $applicationId],
        );
        $connA->transCommit();

        // The real code path, after the release has already won: a
        // confirmEnrollment() attempt for this application must now be
        // rejected — never both succeed for the same seat offer.
        $threw = false;

        try {
            Services::applicationService()->confirmEnrollment($applicationId);
        } catch (BusinessRuleException $e) {
            $threw = true;
            $this->assertSame('APPLICATION_INVALID_STATUS_TRANSITION', $e->errorCode());
        }

        $this->assertTrue($threw, 'confirmEnrollment() must reject an application whose hold was already released.');

        $final = (new ApplicationModel())->find($applicationId);
        $this->assertSame('REJECTED', $final->status, 'The release must be the only write that ever took effect.');
    }

    /**
     * `releaseExpiredHolds()` itself must be safe to invoke twice for the
     * same lapsed hold (e.g. two concurrent Admin Staff clicks) — the
     * second pass must find nothing left to do, not release/promote a
     * second time.
     */
    public function testReleasingTheSameExpiredHoldTwiceOnlyActsOnce(): void
    {
        $db      = Database::connect('tests');
        $now     = Time::now();
        $classId = 999302;

        $applicationId = $this->insertShortlistedApplicationWithExpiredHold($db, $classId, $now);
        $this->applicationIds[] = $applicationId;

        $service = Services::applicationService();

        $first = $service->releaseExpiredHolds();
        $this->assertCount(1, $first->releases);
        $this->assertSame($applicationId, $first->releases[0]['released_application_id']);

        $second = $service->releaseExpiredHolds();
        $this->assertSame([], $second->releases, 'A second pass must find nothing left eligible — no double release.');

        $final = (new ApplicationModel())->find($applicationId);
        $this->assertSame('REJECTED', $final->status);
    }

    private function insertShortlistedApplicationWithExpiredHold(BaseConnection $db, int $classId, Time $now): int
    {
        $db->table('applications')->insert([
            'application_reference_no' => 'APP-CONC-' . random_int(100000, 999999),
            'applicant_name'           => 'Concurrency Candidate',
            'dob'                      => '2015-01-01',
            'class_applied_id'         => $classId,
            'category'                 => 'GENERAL',
            'status'                   => 'SHORTLISTED',
            'submitted_at'             => $now->subDays(1)->toDateTimeString(),
            'hold_expires_at'          => $now->subHours(1)->toDateTimeString(),
            'is_deleted'               => 0,
        ]);

        return (int) $db->insertID();
    }
}
