<?php

declare(strict_types=1);

namespace App\Modules\Admission\Models;

use App\Core\BaseModel;
use App\Modules\Admission\Entities\SeatAllocation;

/**
 * docs/design/admission/Phase-2-Model-Design.md
 */
class SeatAllocationModel extends BaseModel
{
    protected $table          = 'seat_allocations';
    protected $primaryKey     = 'seat_allocation_id';
    protected $returnType     = SeatAllocation::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'class_id',
        'academic_session_id',
        'total_capacity',
        'rte_quota_capacity',
        'seats_filled',
        'rte_seats_filled',
        'created_by',
        'updated_by',
    ];

    public function findByClassAndSession(int $classId, int $academicSessionId): ?SeatAllocation
    {
        return $this->where('class_id', $classId)->where('academic_session_id', $academicSessionId)->first();
    }

    public function existsByClassAndSession(int $classId, int $academicSessionId): bool
    {
        return $this->where('class_id', $classId)
            ->where('academic_session_id', $academicSessionId)
            ->countAllResults() > 0;
    }

    public function existsByClassAndSessionExceptId(int $classId, int $academicSessionId, int $id): bool
    {
        return $this->where('class_id', $classId)
            ->where('academic_session_id', $academicSessionId)
            ->where('seat_allocation_id !=', $id)
            ->countAllResults() > 0;
    }

    /**
     * docs/design/admission/Phase-4-Service-Design.md's decided
     * concurrency strategy: pessimistic row-level locking via
     * `SELECT ... FOR UPDATE` inside a transaction this method owns,
     * followed by a guarded conditional increment — two concurrent calls
     * for the last open seat can never both succeed, because the second
     * caller blocks on the row lock until the first transaction commits,
     * by which point the guard clause below sees the updated counters.
     *
     * Not called by anything in Stage 4 — this is the seam Stage 5's
     * Confirm Enrollment orchestration (docs/design/admission/Phase-6)
     * calls once SIS exists, per Phase 4 §"Decision: concurrency strategy
     * for incrementSeatsFilled".
     *
     * @return bool true if the increment succeeded, false if the seat/RTE
     *              ceiling was already reached (caller's capacity
     *              re-validation, not a database-level exception)
     */
    public function incrementSeatsFilled(int $id, bool $isRte): bool
    {
        $db = $this->db;
        $db->transStart();

        $row = $db->query(
            'SELECT seat_allocation_id, total_capacity, rte_quota_capacity, seats_filled, rte_seats_filled '
                . 'FROM seat_allocations WHERE seat_allocation_id = ? FOR UPDATE',
            [$id],
        )->getRowArray();

        if ($row === null) {
            $db->transComplete();

            return false;
        }

        if ((int) $row['seats_filled'] >= (int) $row['total_capacity']) {
            $db->transComplete();

            return false;
        }

        if ($isRte && (int) $row['rte_seats_filled'] >= (int) $row['rte_quota_capacity']) {
            $db->transComplete();

            return false;
        }

        $set = ['seats_filled' => (int) $row['seats_filled'] + 1];

        if ($isRte) {
            $set['rte_seats_filled'] = (int) $row['rte_seats_filled'] + 1;
        }

        $this->update($id, $set);

        $success = $db->transStatus() !== false;
        $db->transComplete();

        return $success;
    }
}
