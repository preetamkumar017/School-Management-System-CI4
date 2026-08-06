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
}
