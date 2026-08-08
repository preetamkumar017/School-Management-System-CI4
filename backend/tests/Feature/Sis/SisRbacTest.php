<?php

declare(strict_types=1);

namespace Tests\Feature\Sis;

use App\Core\Exceptions\AuthorizationException;
use App\Core\Http\RequestContext;
use App\Modules\Administration\Models\UserModel;
use App\Modules\Sis\Services\StudentService;
use Config\Services;
use Tests\Support\Sis\SisTestCase;

/**
 * @internal
 * docs/ADR/ADR-024-systemwide-rbac-enforcement.md Phase 2 — SIS's
 * `sis.manage` (Tier 1) gates writes; `getStudent()` allows Tier 2 —
 * the Student themself may read their own record (no Guardian login
 * exists in this system).
 */
final class SisRbacTest extends SisTestCase
{
    protected function tearDown(): void
    {
        RequestContext::reset();
        parent::tearDown();
    }

    public function testGetStudentSucceedsForCallerWithManagePermission(): void
    {
        $studentId = $this->createStudentFixture();
        $user      = $this->createUser($this->createRole([StudentService::PERMISSION_MANAGE]));
        RequestContext::setUserId($user['id']);
        RequestContext::setPermissionSet([StudentService::PERMISSION_MANAGE]);

        $response = Services::studentService()->getStudent($studentId);

        $this->assertSame($studentId, $response->studentId);
    }

    public function testGetStudentSucceedsForOwnRecordWithoutManagePermission(): void
    {
        $studentId = $this->createStudentFixture();
        $roleId    = $this->createRole(['read']);
        $userId    = (new UserModel())->insert([
            'username'      => 'student_self_' . uniqid('', true),
            'password_hash' => password_hash(self::TEST_PASSWORD, PASSWORD_BCRYPT),
            'role_id'       => $roleId,
            'owner_type'    => 'STUDENT',
            'owner_ref_id'  => $studentId,
            'status'        => 'ACTIVE',
        ], true);

        RequestContext::setUserId($userId);
        RequestContext::setPermissionSet(['read']);

        $response = Services::studentService()->getStudent($studentId);

        $this->assertSame($studentId, $response->studentId);
    }

    public function testGetStudentRejectedForDifferentStudentOwner(): void
    {
        $studentId       = $this->createStudentFixture();
        $otherStudentId  = $this->createStudentFixture();
        $roleId          = $this->createRole(['read']);
        $userId          = (new UserModel())->insert([
            'username'      => 'other_student_' . uniqid('', true),
            'password_hash' => password_hash(self::TEST_PASSWORD, PASSWORD_BCRYPT),
            'role_id'       => $roleId,
            'owner_type'    => 'STUDENT',
            'owner_ref_id'  => $otherStudentId,
            'status'        => 'ACTIVE',
        ], true);

        RequestContext::setUserId($userId);
        RequestContext::setPermissionSet(['read']);

        $this->assertApiException(
            fn () => Services::studentService()->getStudent($studentId),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );
    }

    public function testUpdateStudentRejectedWithoutManagePermissionEvenForOwnRecord(): void
    {
        $studentId = $this->createStudentFixture();
        $roleId    = $this->createRole(['read']);
        $userId    = (new UserModel())->insert([
            'username'      => 'student_self2_' . uniqid('', true),
            'password_hash' => password_hash(self::TEST_PASSWORD, PASSWORD_BCRYPT),
            'role_id'       => $roleId,
            'owner_type'    => 'STUDENT',
            'owner_ref_id'  => $studentId,
            'status'        => 'ACTIVE',
        ], true);

        RequestContext::setUserId($userId);
        RequestContext::setPermissionSet(['read']);

        $this->assertApiException(
            fn () => Services::studentService()->updateStudent($studentId, new \App\Modules\Sis\DTOs\UpdateStudentRequest(
                'Self Promoted Name',
                '2015-01-01',
                'GENERAL',
                null,
                null,
            )),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );
    }
}
