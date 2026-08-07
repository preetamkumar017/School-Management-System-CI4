<?php

declare(strict_types=1);

namespace Tests\Feature\Examination;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Academic\Models\AcademicSessionModel;
use App\Modules\Fees\Models\InvoiceModel;
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

    /**
     * ADR-014 §2 (BR-SIS-001): fee_closure_confirmed is now computed by
     * querying Fees for an outstanding invoice — an UNPAID invoice for
     * the from_session blocks promotion even though the caller no longer
     * supplies fee_closure_confirmed at all.
     */
    public function testPromotionBlockedByOutstandingInvoiceForFromSession(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $studentId = $this->createStudentFixture();
        $fromClass = $this->createClassFixture(null, 13);
        $toClass   = $this->createClassFixture(null, 14);
        $fromSession = $this->createAcademicSession();
        $toSession   = $this->createAcademicSession();

        (new AcademicSessionModel())->update($fromSession, ['status' => 'CLOSED']);

        (new InvoiceModel())->insert([
            'invoice_no'          => 'INV-TEST-' . random_int(100000, 999999),
            'student_id'          => $studentId,
            'academic_session_id' => $fromSession,
            'total_amount'        => 5000.0,
            'due_date'            => '2026-12-31',
            'status'              => 'UNPAID',
        ], true);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/examination/promotions', [
                'student_id'      => $studentId,
                'from_session_id' => $fromSession,
                'to_session_id'   => $toSession,
                'from_class_id'   => $fromClass,
                'to_class_id'     => $toClass,
            ]),
            BusinessRuleException::class,
            'PROMOTION_CLOSURE_PRECONDITION_NOT_MET',
            422,
        );
    }

    /**
     * The mirror case: a PAID invoice for the from_session is not
     * outstanding, so fee_closure_confirmed computes true and promotion
     * succeeds without the caller ever asserting it.
     */
    public function testPromotionSucceedsWhenInvoiceForFromSessionIsPaid(): void
    {
        $user      = $this->createUser();
        $tokens    = $this->loginAs($user['username']);
        $headers   = $this->authHeaders($tokens['access_token']);
        $studentId = $this->createStudentFixture();
        $fromClass = $this->createClassFixture(null, 15);
        $toClass   = $this->createClassFixture(null, 16);
        $fromSession = $this->createAcademicSession();
        $toSession   = $this->createAcademicSession();

        (new AcademicSessionModel())->update($fromSession, ['status' => 'CLOSED']);

        (new InvoiceModel())->insert([
            'invoice_no'          => 'INV-TEST-' . random_int(100000, 999999),
            'student_id'          => $studentId,
            'academic_session_id' => $fromSession,
            'total_amount'        => 5000.0,
            'due_date'            => '2026-12-31',
            'status'              => 'PAID',
        ], true);

        $create = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/examination/promotions', [
            'student_id'      => $studentId,
            'from_session_id' => $fromSession,
            'to_session_id'   => $toSession,
            'from_class_id'   => $fromClass,
            'to_class_id'     => $toClass,
        ]);
        $create->assertStatus(201);
        $this->assertTrue($this->decode($create)['data']['fee_closure_confirmed']);
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
