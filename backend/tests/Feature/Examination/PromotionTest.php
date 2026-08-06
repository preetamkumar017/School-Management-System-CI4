<?php

declare(strict_types=1);

namespace Tests\Feature\Examination;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Academic\Models\AcademicSessionModel;
use Tests\Support\Examination\ExaminationTestCase;

/**
 * @internal
 */
final class PromotionTest extends ExaminationTestCase
{
    public function testPromotionRequiresBothClosureFlags(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $studentId = $this->createStudentFixture();
        $fromClass = $this->createClassFixture(null, 6);
        $toClass   = $this->createClassFixture(null, 7);
        $fromSession = $this->createAcademicSession();
        $toSession   = $this->createAcademicSession();

        // from_session is still PLANNED/ACTIVE, not CLOSED — academic
        // closure is unconfirmed regardless of the fee flag.
        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/examination/promotions', [
                'student_id'             => $studentId,
                'from_session_id'        => $fromSession,
                'to_session_id'          => $toSession,
                'from_class_id'          => $fromClass,
                'to_class_id'            => $toClass,
                'fee_closure_confirmed'  => true,
            ]),
            BusinessRuleException::class,
            'PROMOTION_CLOSURE_PRECONDITION_NOT_MET',
            422,
        );
    }

    public function testPromotionSucceedsOnceSessionIsClosedAndFeeConfirmed(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $studentId = $this->createStudentFixture();
        $fromClass = $this->createClassFixture(null, 8);
        $toClass   = $this->createClassFixture(null, 9);
        $fromSession = $this->createAcademicSession();
        $toSession   = $this->createAcademicSession();

        (new AcademicSessionModel())->update($fromSession, ['status' => 'CLOSED']);

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/examination/promotions', [
            'student_id'             => $studentId,
            'from_session_id'        => $fromSession,
            'to_session_id'          => $toSession,
            'from_class_id'          => $fromClass,
            'to_class_id'            => $toClass,
            'fee_closure_confirmed'  => true,
        ]);
        $create->assertStatus(201);
        $body = $this->decode($create)['data'];
        $this->assertTrue($body['academic_closure_confirmed']);
        $this->assertTrue($body['fee_closure_confirmed']);
    }

    public function testInvalidClassSequenceIsRejected(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $studentId = $this->createStudentFixture();
        $fromClass = $this->createClassFixture(null, 10);
        $toClass   = $this->createClassFixture(null, 12);
        $fromSession = $this->createAcademicSession();
        $toSession   = $this->createAcademicSession();

        (new AcademicSessionModel())->update($fromSession, ['status' => 'CLOSED']);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/examination/promotions', [
                'student_id'             => $studentId,
                'from_session_id'        => $fromSession,
                'to_session_id'          => $toSession,
                'from_class_id'          => $fromClass,
                'to_class_id'            => $toClass,
                'fee_closure_confirmed'  => true,
            ]),
            BusinessRuleException::class,
            'PROMOTION_INVALID_CLASS_SEQUENCE',
            422,
        );
    }
}
