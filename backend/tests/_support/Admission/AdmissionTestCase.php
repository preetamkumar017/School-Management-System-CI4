<?php

declare(strict_types=1);

namespace Tests\Support\Admission;

use App\Modules\Admission\Models\ApplicationModel;
use App\Modules\Admission\Models\SeatAllocationModel;
use CodeIgniter\I18n\Time;
use Tests\Support\Academic\AcademicTestCase;

/**
 * @internal
 * Reuses AcademicTestCase's createClassFixture()/createAcademicSession()
 * fixtures (Admission's class_applied_id/class_id/academic_session_id are
 * cross-module references to Academic, validated but not FK-constrained).
 * A valid Verhoeff-checksum Aadhaar number for BR-ADM-006 tests: computed
 * offline, not derived at runtime, since generating one requires the same
 * checksum algorithm the code under test also implements.
 */
abstract class AdmissionTestCase extends AcademicTestCase
{
    protected const VALID_AADHAAR = '234567890124';

    protected function createApplicationFixture(
        ?int $classAppliedId = null,
        string $status = 'SUBMITTED',
        string $category = 'GENERAL',
    ): int {
        return (new ApplicationModel())->insert([
            'application_reference_no' => 'APP-TEST-' . random_int(100000, 999999),
            'applicant_name'           => 'Test Applicant',
            'dob'                      => '2015-01-01',
            'class_applied_id'         => $classAppliedId ?? $this->createClassFixture(),
            'category'                 => $category,
            'status'                   => $status,
            'submitted_at'             => Time::now()->toDateTimeString(),
        ], true);
    }

    protected function createSeatAllocationFixture(
        ?int $classId = null,
        ?int $academicSessionId = null,
        int $totalCapacity = 40,
        int $rteQuotaCapacity = 10,
    ): int {
        return (new SeatAllocationModel())->insert([
            'class_id'            => $classId ?? $this->createClassFixture(),
            'academic_session_id' => $academicSessionId ?? $this->createAcademicSession(),
            'total_capacity'      => $totalCapacity,
            'rte_quota_capacity'  => $rteQuotaCapacity,
        ], true);
    }
}
