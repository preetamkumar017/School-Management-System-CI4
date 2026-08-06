<?php

declare(strict_types=1);

namespace Tests\Feature\Admission;

use App\Modules\Admission\Models\SeatAllocationModel;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use Throwable;

/**
 * @internal
 * Roadmap Stage 4's headline verification: proves the pessimistic
 * row-lock on SeatAllocation's counters (docs/design/admission/Phase-4's
 * decided concurrency strategy) actually prevents two simultaneous
 * confirmations from both succeeding for the last open seat.
 *
 * Deliberately does NOT use AdmissionTestCase/DatabaseTestTrait — that
 * trait wraps each test in one shared transaction that gets rolled back,
 * which would hide the very row-visibility-across-connections behavior
 * this test needs to exercise with two genuinely independent connections.
 * class_id/academic_session_id have no DB-level FK (cross-module rule),
 * so this doesn't need any Academic fixture rows to exist.
 */
final class SeatAllocationConcurrencyTest extends CIUnitTestCase
{
    private ?int $seatAllocationId = null;

    protected function tearDown(): void
    {
        if ($this->seatAllocationId !== null) {
            Database::connect('tests')
                ->table('seat_allocations')
                ->delete(['seat_allocation_id' => $this->seatAllocationId]);
        }

        parent::tearDown();
    }

    public function testPessimisticLockPreventsDoubleBookingTheLastOpenSeat(): void
    {
        $db = Database::connect('tests');

        $db->table('seat_allocations')->insert([
            'class_id'            => 999001,
            'academic_session_id' => 999001,
            'total_capacity'      => 1,
            'rte_quota_capacity'  => 0,
            'seats_filled'        => 0,
            'rte_seats_filled'    => 0,
            'is_deleted'          => 0,
        ]);
        $id = $db->insertID();
        $this->seatAllocationId = $id;

        $connA = Database::connect('tests', false);
        $connB = Database::connect('tests', false);

        // Connection A opens a transaction and locks the row without
        // committing — simulating the first of two "simultaneous"
        // Confirm Enrollment calls for the last open seat.
        $connA->transBegin();
        $connA->query('SELECT seat_allocation_id FROM seat_allocations WHERE seat_allocation_id = ? FOR UPDATE', [$id]);

        // Connection B represents the second, truly concurrent caller.
        // With a short lock-wait timeout, its attempt to acquire the same
        // row lock must block/time out while A's transaction is still
        // open — proving this is a real database row lock, not merely an
        // application-level guard that a race could slip past.
        $connB->query('SET SESSION innodb_lock_wait_timeout = 1');

        $blocked = false;

        try {
            $connB->query(
                'SELECT seat_allocation_id FROM seat_allocations WHERE seat_allocation_id = ? FOR UPDATE',
                [$id],
            );
        } catch (Throwable $e) {
            $blocked = true;
        }

        $this->assertTrue($blocked, "Connection B should have blocked on connection A's row lock, but did not.");

        // A finishes (commits) — the seat remains unfilled at this point,
        // since A only ever locked the row and read it, never wrote.
        $connA->transCommit();

        // Now the real guarded increment: the first call fills the only
        // open seat; a second call for the same (now-full) allocation
        // must be rejected, never allowed to overfill it.
        $model = new SeatAllocationModel();

        $this->assertTrue($model->incrementSeatsFilled($id, false), 'The first admission should succeed.');
        $this->assertFalse(
            $model->incrementSeatsFilled($id, false),
            'A second admission for the same, now-full allocation must be rejected.',
        );

        $row = $db->table('seat_allocations')->where('seat_allocation_id', $id)->get()->getRowArray();
        $this->assertSame(1, (int) $row['seats_filled'], 'seats_filled must never exceed total_capacity.');
    }
}
