<?php

declare(strict_types=1);

namespace Tests\Feature\Examination;

use App\Core\Exceptions\AuthorizationException;
use App\Core\Http\RequestContext;
use App\Modules\Administration\Models\UserModel;
use App\Modules\Examination\Services\ExamService;
use App\Modules\Examination\Services\MarksRecordService;
use Config\Services;
use Tests\Support\Examination\ExaminationTestCase;

/**
 * @internal
 * docs/ADR/ADR-024-systemwide-rbac-enforcement.md Phase 2 —
 * `examination.manage` (Tier 1) gates entry/lock/reevaluate;
 * `getMarksRecord()` allows Tier 2 — a Student may read their own marks.
 */
final class ExaminationRbacTest extends ExaminationTestCase
{
    protected function tearDown(): void
    {
        RequestContext::reset();
        parent::tearDown();
    }

    public function testGetMarksRecordSucceedsForCallerWithManagePermission(): void
    {
        $examId         = $this->createExamFixture();
        $marksRecordId  = $this->createMarksRecordFixture($examId);
        $user           = $this->createUser($this->createRole([MarksRecordService::PERMISSION_MANAGE]));
        RequestContext::setUserId($user['id']);
        RequestContext::setPermissionSet([MarksRecordService::PERMISSION_MANAGE]);

        $response = Services::marksRecordService()->getMarksRecord($marksRecordId);

        $this->assertSame($marksRecordId, $response->marksRecordId);
    }

    public function testGetMarksRecordSucceedsForOwningStudent(): void
    {
        $studentId     = $this->createStudentFixture();
        $examId        = $this->createExamFixture();
        $marksRecordId = $this->createMarksRecordFixture($examId, $studentId);

        $roleId = $this->createRole(['read']);
        $userId = (new UserModel())->insert([
            'username'      => 'exam_self_' . uniqid('', true),
            'password_hash' => password_hash(self::TEST_PASSWORD, PASSWORD_BCRYPT),
            'role_id'       => $roleId,
            'owner_type'    => 'STUDENT',
            'owner_ref_id'  => $studentId,
            'status'        => 'ACTIVE',
        ], true);
        RequestContext::setUserId($userId);
        RequestContext::setPermissionSet(['read']);

        $response = Services::marksRecordService()->getMarksRecord($marksRecordId);

        $this->assertSame($marksRecordId, $response->marksRecordId);
    }

    public function testGetMarksRecordRejectedForDifferentStudentOwner(): void
    {
        $ownerStudentId = $this->createStudentFixture();
        $otherStudentId = $this->createStudentFixture();
        $examId         = $this->createExamFixture();
        $marksRecordId  = $this->createMarksRecordFixture($examId, $ownerStudentId);

        $roleId = $this->createRole(['read']);
        $userId = (new UserModel())->insert([
            'username'      => 'exam_other_' . uniqid('', true),
            'password_hash' => password_hash(self::TEST_PASSWORD, PASSWORD_BCRYPT),
            'role_id'       => $roleId,
            'owner_type'    => 'STUDENT',
            'owner_ref_id'  => $otherStudentId,
            'status'        => 'ACTIVE',
        ], true);
        RequestContext::setUserId($userId);
        RequestContext::setPermissionSet(['read']);

        $this->assertApiException(
            fn () => Services::marksRecordService()->getMarksRecord($marksRecordId),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );
    }

    public function testActivateExamRejectedForCallerWithoutManagePermission(): void
    {
        $examId = $this->createExamFixture();
        $user   = $this->createUser($this->createRole(['read']));
        RequestContext::setUserId($user['id']);
        RequestContext::setPermissionSet(['read']);

        $this->assertApiException(
            fn () => Services::examService()->activateExam($examId),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );
    }

    public function testActivateExamSucceedsForCallerWithManagePermission(): void
    {
        $examId = $this->createExamFixture();
        $user   = $this->createUser($this->createRole([ExamService::PERMISSION_MANAGE]));
        RequestContext::setUserId($user['id']);
        RequestContext::setPermissionSet([ExamService::PERMISSION_MANAGE]);

        $response = Services::examService()->activateExam($examId);

        $this->assertSame('ACTIVE', $response->status);
    }
}
