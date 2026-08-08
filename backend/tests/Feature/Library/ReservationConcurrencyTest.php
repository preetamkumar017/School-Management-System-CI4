<?php

declare(strict_types=1);

namespace Tests\Feature\Library;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Http\RequestContext;
use App\Modules\Administration\Models\RoleModel;
use App\Modules\Administration\Models\UserModel;
use App\Modules\HrPayroll\Models\DepartmentModel;
use App\Modules\HrPayroll\Models\DesignationModel;
use App\Modules\HrPayroll\Models\EmployeeModel;
use App\Modules\Library\DTOs\IssueBookRequest;
use App\Modules\Library\Models\BookModel;
use App\Modules\Library\Models\ReservationModel;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use Config\Services;
use Throwable;

/**
 * @internal
 * docs/ADR/ADR-017-library-reservation-queue.md §6 — proves the race
 * between `ReservationService::processExpiredNotifications()` expiring a
 * Notified reservation and `BookIssueService::issueBook()` trying to
 * fulfil that same reservation genuinely cannot both win, using two
 * independent database connections — the same style Stage 12's
 * `SeatHoldConcurrencyTest` established.
 *
 * Deliberately does NOT use LibraryTestCase — that hierarchy wraps each
 * test in one shared transaction rolled back at teardown, which would
 * hide the row-visibility-across-connections behavior this test needs
 * two genuinely independent connections to exercise.
 */
final class ReservationConcurrencyTest extends CIUnitTestCase
{
    private ?int $bookId          = null;
    private ?int $notifiedReservationId = null;
    private ?int $waitingReservationId  = null;
    private ?int $notifiedEmployeeId    = null;
    private ?int $waitingEmployeeId     = null;
    private ?int $userId = null;
    private ?int $roleId = null;

    protected function setUp(): void
    {
        parent::setUp();

        // BookIssueService::issueBook()/ReservationService's writes go
        // through AuditService, whose performed_by column is a real FK
        // to users.user_id — normally populated by JwtAuthFilter. This
        // test calls the Service directly (no HTTP layer, deliberately
        // — see the class docblock), so it must stand in with a real
        // User row of its own.
        $this->roleId = (new RoleModel())->insert([
            'role_name'      => 'Role ' . uniqid('', true),
            'is_system_role' => false,
            'permission_set' => ['read', 'create', 'update', 'delete'],
        ], true);

        $this->userId = (new UserModel())->insert([
            'username'      => 'rsvconc_' . uniqid('', true),
            'password_hash' => password_hash('irrelevant', PASSWORD_BCRYPT),
            'role_id'       => $this->roleId,
            'owner_type'    => 'EMPLOYEE',
            'owner_ref_id'  => 1,
            'status'        => 'ACTIVE',
        ], true);

        RequestContext::setUserId($this->userId);
        RequestContext::setPermissionSet(['library.manage', 'hr_payroll.manage']);

        $departmentId  = (new DepartmentModel())->insert(['department_name' => 'Dept ' . uniqid('', true)], true);
        $designationId = (new DesignationModel())->insert(['designation_name' => 'Desig ' . uniqid('', true)], true);

        $this->notifiedEmployeeId = (new EmployeeModel())->insert([
            'employee_code'         => 'EMP-' . random_int(100000, 999999),
            'full_name'             => 'Notified Employee',
            'department_id'         => $departmentId,
            'designation_id'        => $designationId,
            'joining_date'          => '2020-01-01',
            'salary_structure_json' => ['basic' => 30000],
            'status'                => 'Active',
        ], true);

        $this->waitingEmployeeId = (new EmployeeModel())->insert([
            'employee_code'         => 'EMP-' . random_int(100000, 999999),
            'full_name'             => 'Waiting Employee',
            'department_id'         => $departmentId,
            'designation_id'        => $designationId,
            'joining_date'          => '2020-01-01',
            'salary_structure_json' => ['basic' => 30000],
            'status'                => 'Active',
        ], true);

        $this->bookId = (new BookModel())->insert([
            'barcode'        => 'CONC-' . random_int(100000, 999999),
            'title'          => 'Concurrency Test Book',
            'author'         => 'Author',
            'classification' => 'Circulating',
            'is_available'   => true,
        ], true);

        $now = Time::now();

        $this->notifiedReservationId = (new ReservationModel())->insert([
            'book_id'                 => $this->bookId,
            'borrower_type'           => 'Employee',
            'borrower_ref_id'         => $this->notifiedEmployeeId,
            'requested_at'            => $now->subHours(50)->toDateTimeString(),
            'status'                  => 'Notified',
            'notified_at'             => $now->subHours(50)->toDateTimeString(),
            'notification_expires_at' => $now->subHours(2)->toDateTimeString(),
        ], true);

        $this->waitingReservationId = (new ReservationModel())->insert([
            'book_id'         => $this->bookId,
            'borrower_type'   => 'Employee',
            'borrower_ref_id' => $this->waitingEmployeeId,
            'requested_at'    => $now->subHours(49)->toDateTimeString(),
            'status'          => 'Waiting',
        ], true);
    }

    protected function tearDown(): void
    {
        RequestContext::reset();

        Database::connect('tests')->table('reservations')
            ->whereIn('reservation_id', [$this->notifiedReservationId, $this->waitingReservationId])
            ->delete();
        Database::connect('tests')->table('book_issues')->where('book_id', $this->bookId)->delete();
        Database::connect('tests')->table('books')->where('book_id', $this->bookId)->delete();
        Database::connect('tests')->table('employees')
            ->whereIn('employee_id', [$this->notifiedEmployeeId, $this->waitingEmployeeId])
            ->delete();

        if ($this->userId !== null) {
            (new UserModel())->delete($this->userId);
        }

        if ($this->roleId !== null) {
            (new RoleModel())->delete($this->roleId);
        }

        parent::tearDown();
    }

    /**
     * Connection A holds the exact row lock `expireOneNotification()`
     * takes (via `ReservationModel::lockNotifiedForBook()`)
     * mid-transaction, without committing — simulating
     * `processExpiredNotifications()` in flight. Connection B then
     * attempts the exact lock query `issueBook()` issues for the same
     * book and must block, proving this is a real database row lock.
     * Connection A then completes the expiry + promotion it was holding
     * the lock for and commits; only then does the real
     * `BookIssueService::issueBook()` run — first for the originally
     * notified employee (must now be rejected, their window already
     * lapsed and the next holder is the one with priority), then for the
     * newly-promoted employee (must succeed and fulfil that reservation)
     * — proving the expiry win is the only write that ever took effect.
     */
    public function testRowLockPreventsIssueBookFromFulfillingAnAlreadyExpiredNotification(): void
    {
        $connA = Database::connect('tests', false);
        $connB = Database::connect('tests', false);

        // Connection A: the exact lock expireOneNotification() takes,
        // held open (not yet committed).
        $connA->transBegin();
        $connA->query(
            'SELECT reservation_id, book_id, borrower_type, borrower_ref_id, status, notification_expires_at '
                . 'FROM reservations WHERE book_id = ? AND status = ? FOR UPDATE',
            [$this->bookId, 'Notified'],
        );

        // Connection B: the exact lock issueBook() takes for the same
        // book, with a short lock-wait timeout so a genuine block is
        // observable within the test instead of hanging.
        $connB->query('SET SESSION innodb_lock_wait_timeout = 1');

        $blocked = false;

        try {
            $connB->query(
                'SELECT reservation_id, book_id, borrower_type, borrower_ref_id, status, notification_expires_at '
                    . 'FROM reservations WHERE book_id = ? AND status = ? FOR UPDATE',
                [$this->bookId, 'Notified'],
            );
        } catch (Throwable $e) {
            $blocked = true;
        }

        $this->assertTrue(
            $blocked,
            "Connection B's issueBook()-style lock attempt should have blocked on A's in-flight expiry, but did not.",
        );

        // Connection A completes the expiry + promotion it was holding
        // the lock for (mirrors expireOneNotification() + notifyNextInQueue())
        // and commits.
        $now = Time::now();
        $connA->query('UPDATE reservations SET status = ? WHERE reservation_id = ?', ['Expired', $this->notifiedReservationId]);
        $connA->query(
            'UPDATE reservations SET status = ?, notified_at = ?, notification_expires_at = ? WHERE reservation_id = ?',
            ['Notified', $now->toDateTimeString(), $now->addHours(48)->toDateTimeString(), $this->waitingReservationId],
        );
        $connA->transCommit();

        // The real code path, after the expiry+promotion has already
        // won: the originally-notified employee no longer has priority.
        $threw = false;

        try {
            Services::bookIssueService()->issueBook(new IssueBookRequest($this->bookId, 'Employee', $this->notifiedEmployeeId, '2026-09-01'));
        } catch (BusinessRuleException $e) {
            $threw = true;
            $this->assertSame('BOOK_RESERVED_FOR_ANOTHER_BORROWER', $e->errorCode());
        }

        $this->assertTrue($threw, 'issueBook() must reject the originally-notified borrower once their window already lapsed.');

        // The newly-promoted employee succeeds and fulfils their reservation.
        $response = Services::bookIssueService()->issueBook(new IssueBookRequest($this->bookId, 'Employee', $this->waitingEmployeeId, '2026-09-01'));
        $this->assertSame('Issued', $response->status);

        $finalNotified = (new ReservationModel())->find($this->notifiedReservationId);
        $finalWaiting  = (new ReservationModel())->find($this->waitingReservationId);

        $this->assertSame('Expired', $finalNotified->status, 'The expiry must be the only write that ever took effect for the original reservation.');
        $this->assertSame('Fulfilled', $finalWaiting->status, 'The promoted reservation must end up Fulfilled, not still Notified.');
    }
}
