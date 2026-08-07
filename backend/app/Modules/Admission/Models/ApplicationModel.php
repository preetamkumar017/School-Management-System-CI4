<?php

declare(strict_types=1);

namespace App\Modules\Admission\Models;

use App\Core\BaseModel;
use App\Modules\Admission\Entities\Application;

/**
 * docs/design/admission/Phase-2-Model-Design.md
 * Application is Transaction-classified data (Phase 2) — its listing is
 * paginated, unlike Academic's/Administration's Master-data Models.
 */
class ApplicationModel extends BaseModel
{
    protected $table          = 'applications';
    protected $primaryKey     = 'application_id';
    protected $returnType     = Application::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'application_reference_no',
        'applicant_name',
        'dob',
        'class_applied_id',
        'aadhaar_number',
        'category',
        'status',
        'submitted_at',
        'decided_at',
        'hold_expires_at',
        'created_by',
        'updated_by',
    ];

    public function findByReferenceNo(string $value): ?Application
    {
        return $this->where('application_reference_no', $value)->first();
    }

    public function existsByReferenceNo(string $value): bool
    {
        return $this->where('application_reference_no', $value)->countAllResults() > 0;
    }

    public function existsByAadhaarNumber(string $value): bool
    {
        return $this->where('aadhaar_number', $value)->countAllResults() > 0;
    }

    /**
     * FR-05a/BR-ADM-006 duplicate-identity re-check at Confirm Enrollment
     * time (Phase 6 §3) — scoped to already-ADMITTED applications, since
     * that's what "duplicate applicant already enrolled" actually means.
     */
    public function existsByAadhaarNumberAmongAdmittedExceptId(string $value, int $exceptId): bool
    {
        return $this->where('aadhaar_number', $value)
            ->where('status', Application::STATUS_ADMITTED)
            ->where('application_id !=', $exceptId)
            ->countAllResults() > 0;
    }

    /**
     * @return list<Application>
     */
    public function findByStatus(string $status, ?int $classId = null): array
    {
        $builder = $this->where('status', $status);

        if ($classId !== null) {
            $builder = $builder->where('class_applied_id', $classId);
        }

        return $builder->orderBy('submitted_at', 'DESC')->findAll();
    }

    /**
     * Paginated staff queue (Phase 4's listApplications) — filters are
     * optional, unlike findByStatus's required $status.
     *
     * @return list<Application>
     */
    public function paginateByFilters(?string $status, ?int $classId, int $page, int $perPage): array
    {
        $builder = $this->orderBy('submitted_at', 'DESC');

        if ($status !== null) {
            $builder = $builder->where('status', $status);
        }

        if ($classId !== null) {
            $builder = $builder->where('class_applied_id', $classId);
        }

        return $builder->paginate($perPage, 'default', $page);
    }

    /**
     * docs/ADR/ADR-016-admission-seat-hold-and-waitlist.md §4/§5 — the
     * candidate-gathering read for `releaseExpiredHolds()`. Deliberately
     * a plain (non-locked) read: each candidate id is re-verified under
     * its own row lock inside the per-application transaction that
     * actually acts on it, matching `incrementSeatsFilled()`'s own
     * "guarded read, then lock-and-recheck" shape.
     *
     * @return list<Application>
     */
    public function findExpiredHolds(string $nowDateTime): array
    {
        return $this->where('status', Application::STATUS_SHORTLISTED)
            ->where('hold_expires_at IS NOT NULL')
            ->where('hold_expires_at <=', $nowDateTime)
            ->orderBy('hold_expires_at', 'ASC')
            ->findAll();
    }

    /**
     * BR-ADM-008 ranking (ADR-016 §3): strictly `submitted_at` ascending,
     * scoped to the class a vacated seat belongs to — no priority
     * category exists on `Application` to rank on instead.
     */
    public function findEarliestWaitlistedForClass(int $classAppliedId): ?Application
    {
        return $this->where('status', Application::STATUS_WAITLISTED)
            ->where('class_applied_id', $classAppliedId)
            ->orderBy('submitted_at', 'ASC')
            ->first();
    }

    /**
     * ADR-016 §4/§5's row-lock discipline, reusing
     * `SeatAllocationModel::incrementSeatsFilled()`'s exact raw-SQL
     * `SELECT ... FOR UPDATE` shape (must run inside a transaction the
     * caller owns). Returns the raw row (not a hydrated Entity) since
     * callers only need `status`/`hold_expires_at` to re-verify
     * eligibility under the lock before writing.
     *
     * @return array<string, mixed>|null
     */
    public function lockForUpdate(int $id): ?array
    {
        return $this->db->query(
            'SELECT application_id, status, hold_expires_at, class_applied_id '
                . 'FROM applications WHERE application_id = ? FOR UPDATE',
            [$id],
        )->getRowArray();
    }
}
