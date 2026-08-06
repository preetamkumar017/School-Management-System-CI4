<?php

declare(strict_types=1);

namespace Tests\Support\Sis;

use App\Modules\Sis\Models\GuardianModel;
use App\Modules\Sis\Models\StudentModel;
use Tests\Support\Admission\AdmissionTestCase;

/**
 * @internal
 * Reuses AdmissionTestCase's/AcademicTestCase's fixtures — Student rows
 * are cross-module descendants of Academic's Class/AcademicSession and
 * Admission's Application, all of which this hierarchy already knows how
 * to fabricate.
 */
abstract class SisTestCase extends AdmissionTestCase
{
    protected function createGuardianFixture(?string $mobileNumber = null): int
    {
        return (new GuardianModel())->insert([
            'full_name'     => 'Test Guardian',
            'relationship'  => 'FATHER',
            'mobile_number' => $mobileNumber ?? (string) random_int(6000000000, 9999999999),
            'email'         => null,
        ], true);
    }

    /**
     * A DRAFT Student stub, inserted directly (not via createStudentStub,
     * which requires a real confirmed Application) — used by tests that
     * only care about Student's own post-creation operations.
     */
    protected function createStudentFixture(
        ?int $applicationId = null,
        ?int $sectionId = null,
        string $status = 'DRAFT',
        string $category = 'GENERAL',
    ): int {
        return (new StudentModel())->insert([
            'admission_number' => 'ADM-TEST-' . random_int(100000, 999999),
            'full_name'        => 'Test Student',
            'dob'              => '2015-01-01',
            'section_id'       => $sectionId,
            'application_id'   => $applicationId ?? $this->createApplicationFixture(),
            'category'         => $category,
            'status'           => $status,
        ], true);
    }
}
