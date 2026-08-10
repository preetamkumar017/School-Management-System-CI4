<?php

declare(strict_types=1);

namespace Tests\Support\Academic;

use App\Modules\Academic\Models\AcademicSessionModel;
use App\Modules\Academic\Models\ClassModel;
use App\Modules\Academic\Models\GradingSchemeModel;
use App\Modules\Academic\Models\SectionModel;
use App\Modules\Academic\Models\SubjectModel;
use Tests\Support\Administration\AdministrationTestCase;

/**
 * @internal
 * Reuses AdministrationTestCase's auth helpers (createUser/loginAs) — login
 * and RBAC are Administration's own module, not duplicated here.
 */
abstract class AcademicTestCase extends AdministrationTestCase
{
    protected function createAcademicSession(
        ?string $sessionName = null,
        string $startDate = '2026-04-01',
        string $endDate = '2027-03-31',
    ): int {
        return (new AcademicSessionModel())->insert([
            'session_name' => $sessionName ?? ('20' . random_int(10, 99) . '-' . random_int(10, 99)),
            'start_date'   => $startDate,
            'end_date'     => $endDate,
        ], true);
    }

    protected function createClassFixture(?string $className = null, ?int $sequenceOrder = null): int
    {
        return (new ClassModel())->insert([
            'class_name'     => $className ?? ('C' . uniqid('', false)),
            'sequence_order' => $sequenceOrder ?? random_int(1000, 999999),
        ], true);
    }

    protected function createSection(?int $classId = null, string $sectionName = 'A', int $capacity = 40): int
    {
        return (new SectionModel())->insert([
            'class_id'     => $classId ?? $this->createClassFixture(),
            'section_name' => $sectionName,
            'capacity'     => $capacity,
        ], true);
    }

    protected function createSubject(?string $subjectName = null, ?string $subjectCode = null): int
    {
        return (new SubjectModel())->insert([
            'subject_name'        => $subjectName ?? ('Subject ' . uniqid('', true)),
            'subject_code'        => $subjectCode ?? ('S' . random_int(100000, 999999)),
            'subject_category_id' => null,
            'is_language_subject' => 0,
            'stream_applicability' => 'ALL',
        ], true);
    }

    /**
     * @param array<string, string>|null $gradeBandJson
     */
    protected function createGradingScheme(?string $schemeName = null, ?array $gradeBandJson = null): int
    {
        return (new GradingSchemeModel())->insert([
            'scheme_name'     => $schemeName ?? ('Scheme ' . uniqid('', true)),
            'board_type'      => 'CBSE',
            'grade_band_json' => $gradeBandJson ?? ['A1' => '91-100', 'A2' => '81-90'],
        ], true);
    }
}
