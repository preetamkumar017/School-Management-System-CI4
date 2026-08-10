<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Exceptions\ValidationException;
use App\Modules\Academic\Models\GradingSchemeModel;
use Tests\Support\Academic\AcademicTestCase;

/**
 * @internal
 */
final class GradingSchemeTest extends AcademicTestCase
{
    public function testCreateUpdateAndReadGradingScheme(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/academic/grading-schemes', [
            'scheme_name'     => 'CBSE Standard Grading ' . uniqid('', true),
            'board_type'      => 'CBSE',
            'grade_band_json' => ['A1' => '91-100', 'A2' => '81-90', 'B1' => '71-80'],
        ]);
        $create->assertStatus(201);
        $schemeId = $this->decode($create)['data']['grading_scheme_id'];

        $update = $this->withHeaders($headers)->withBodyFormat('json')
            ->patch('api/v1/academic/grading-schemes/' . $schemeId, [
                'board_type'      => 'CBSE',
                'grade_band_json' => ['A1' => '90-100', 'A2' => '80-89'],
            ]);
        $update->assertStatus(200);
        $this->assertSame(['A1' => '90-100', 'A2' => '80-89'], $this->decode($update)['data']['grade_band_json']);
    }

    public function testOverlappingGradeBandsAreRejected(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/academic/grading-schemes', [
                'scheme_name'     => 'Overlapping Scheme ' . uniqid('', true),
                'board_type'      => 'CBSE',
                'grade_band_json' => ['A1' => '80-100', 'A2' => '75-90'],
            ]),
            ValidationException::class,
            'VALIDATION_FAILED',
            422,
        );
    }

    /**
     * Phase 6 closure criteria's headline test: once a GradingScheme is
     * referenced by a closed Exam, it becomes immutable — the caller must
     * create a new scheme instead of mutating this one. Examination isn't
     * implemented yet (Stage 6), so isReferencedByClosedExam always
     * returns false in this codebase today; this test exercises that
     * seam directly against the Model to prove the Service-layer
     * immutability gate itself is wired correctly, independent of
     * Examination's future implementation.
     */
    public function testUpdateIsRejectedOnceLockedByAClosedExam(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);
        $schemeId = $this->createGradingScheme('Locked Scheme ' . uniqid('', true));

        $lockedModel = new class () extends GradingSchemeModel {
            public function isReferencedByClosedExam(int $schemeId): bool
            {
                return true;
            }
        };

        $service = new \App\Modules\Academic\Services\GradingSchemeService(
            $lockedModel,
            \Config\Services::auditService(),
            \Config\Services::moduleAuthorizer(),
        );

        \App\Core\Http\RequestContext::setPermissionSet(['academic.manage']);

        $this->assertApiException(
            fn () => $service->updateGradingScheme($schemeId, new \App\Modules\Academic\DTOs\UpdateGradingSchemeRequest(
                'CBSE',
                ['A1' => '91-100'],
            )),
            BusinessRuleException::class,
            'GRADING_SCHEME_LOCKED_BY_CLOSED_EXAM',
            422,
        );
    }
}
