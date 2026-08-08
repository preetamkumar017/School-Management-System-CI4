<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Exceptions\AuthorizationException;
use App\Core\Http\RequestContext;
use App\Modules\Administration\Models\UserModel;
use App\Modules\Attendance\Services\AttendanceService;
use Config\Services;
use Tests\Support\Attendance\AttendanceTestCase;

/**
 * @internal
 * docs/ADR/ADR-024-systemwide-rbac-enforcement.md Phase 2 —
 * `attendance.manage` (Tier 1) gates marking/locking/correcting;
 * `getAttendanceRecord()` allows Tier 2 — a Student may read their own
 * attendance.
 */
final class AttendanceRbacTest extends AttendanceTestCase
{
    protected function tearDown(): void
    {
        RequestContext::reset();
        parent::tearDown();
    }

    public function testGetAttendanceRecordSucceedsForCallerWithManagePermission(): void
    {
        $recordId = $this->createAttendanceRecordFixture();
        $user     = $this->createUser($this->createRole([AttendanceService::PERMISSION_MANAGE]));
        RequestContext::setUserId($user['id']);
        RequestContext::setPermissionSet([AttendanceService::PERMISSION_MANAGE]);

        $response = Services::attendanceService()->getAttendanceRecord($recordId);

        $this->assertSame($recordId, $response->attendanceRecordId);
    }

    public function testGetAttendanceRecordSucceedsForOwningStudent(): void
    {
        $studentId = $this->createStudentFixture();
        $recordId  = $this->createAttendanceRecordFixture($studentId);

        $roleId = $this->createRole(['read']);
        $userId = (new UserModel())->insert([
            'username'      => 'att_self_' . uniqid('', true),
            'password_hash' => password_hash(self::TEST_PASSWORD, PASSWORD_BCRYPT),
            'role_id'       => $roleId,
            'owner_type'    => 'STUDENT',
            'owner_ref_id'  => $studentId,
            'status'        => 'ACTIVE',
        ], true);
        RequestContext::setUserId($userId);
        RequestContext::setPermissionSet(['read']);

        $response = Services::attendanceService()->getAttendanceRecord($recordId);

        $this->assertSame($recordId, $response->attendanceRecordId);
    }

    public function testGetAttendanceRecordRejectedForDifferentStudentOwner(): void
    {
        $ownerStudentId = $this->createStudentFixture();
        $otherStudentId = $this->createStudentFixture();
        $recordId       = $this->createAttendanceRecordFixture($ownerStudentId);

        $roleId = $this->createRole(['read']);
        $userId = (new UserModel())->insert([
            'username'      => 'att_other_' . uniqid('', true),
            'password_hash' => password_hash(self::TEST_PASSWORD, PASSWORD_BCRYPT),
            'role_id'       => $roleId,
            'owner_type'    => 'STUDENT',
            'owner_ref_id'  => $otherStudentId,
            'status'        => 'ACTIVE',
        ], true);
        RequestContext::setUserId($userId);
        RequestContext::setPermissionSet(['read']);

        $this->assertApiException(
            fn () => Services::attendanceService()->getAttendanceRecord($recordId),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );
    }

    public function testLockAttendanceRejectedForCallerWithoutManagePermission(): void
    {
        $recordId = $this->createAttendanceRecordFixture();
        $user     = $this->createUser($this->createRole(['read']));
        RequestContext::setUserId($user['id']);
        RequestContext::setPermissionSet(['read']);

        $this->assertApiException(
            fn () => Services::attendanceService()->lockAttendance($recordId),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );
    }
}
