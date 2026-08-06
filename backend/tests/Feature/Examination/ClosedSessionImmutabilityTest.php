<?php

declare(strict_types=1);

namespace Tests\Feature\Examination;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Academic\Models\AcademicSessionModel;
use Tests\Support\Examination\ExaminationTestCase;

/**
 * @internal
 * docs/ADR/ADR-005-examination-module-scope-decisions.md §1 — BR-SIS-004,
 * resolved as closed-academic-session immutability, enforced here (not in
 * SIS, which owns no historical/transactional entity).
 */
final class ClosedSessionImmutabilityTest extends ExaminationTestCase
{
    public function testMutatingAnExamInAClosedSessionIsRejectedWithoutAnOverrideReason(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $sessionId = $this->createAcademicSession();
        $examId    = $this->createExamFixture(null, $sessionId, null, null, 'CONFIGURED');

        (new AcademicSessionModel())->update($sessionId, ['status' => 'CLOSED']);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->post("api/v1/examination/exams/{$examId}/activate"),
            BusinessRuleException::class,
            'RECORD_LOCKED_BY_CLOSED_SESSION',
            422,
        );
    }

    public function testOverrideReasonAllowsTheMutationToProceed(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $sessionId = $this->createAcademicSession();
        $examId    = $this->createExamFixture(null, $sessionId, null, null, 'CONFIGURED');

        (new AcademicSessionModel())->update($sessionId, ['status' => 'CLOSED']);

        $activate = $this->withHeaders($headers)->withBodyFormat('json')
            ->post("api/v1/examination/exams/{$examId}/activate", [
                'override_reason' => 'Late correction authorized by Academic Head.',
            ]);
        $activate->assertStatus(200);
        $this->assertSame('ACTIVE', $this->decode($activate)['data']['status']);
    }
}
