<?php

declare(strict_types=1);

namespace Tests\Feature\Communication;

use App\Core\Exceptions\AuthorizationException;
use App\Core\Http\RequestContext;
use App\Modules\Administration\Models\UserModel;
use App\Modules\Communication\DTOs\CreateCircularRequest;
use App\Modules\Communication\Services\CircularService;
use App\Modules\Communication\Services\NotificationLogService;
use Config\Services;
use Tests\Support\Communication\CommunicationTestCase;

/**
 * @internal
 * docs/ADR/ADR-024-systemwide-rbac-enforcement.md Phase 2 —
 * `communication.manage` (Tier 1) gates circulars and dispatch;
 * `getNotificationLog()` allows Tier 2 — the recipient may read their own log.
 */
final class CommunicationRbacTest extends CommunicationTestCase
{
    protected function tearDown(): void
    {
        RequestContext::reset();
        parent::tearDown();
    }

    public function testCreateCircularRejectedWithoutManagePermission(): void
    {
        $user = $this->createUser($this->createRole(['read']));
        RequestContext::setUserId($user['id']);
        RequestContext::setPermissionSet(['read']);

        $this->assertApiException(
            fn () => Services::circularService()->createCircular(new CreateCircularRequest(
                $user['id'],
                'Announcement',
                'Test Title ' . uniqid('', true),
                'Test Body Content',
                'All',
            )),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );
    }

    public function testCreateCircularSucceedsWithManagePermission(): void
    {
        $user = $this->createUser($this->createRole([CircularService::PERMISSION_MANAGE]));
        RequestContext::setUserId($user['id']);
        RequestContext::setPermissionSet([CircularService::PERMISSION_MANAGE]);

        $response = Services::circularService()->createCircular(new CreateCircularRequest(
            $user['id'],
            'Announcement',
            'Test Title ' . uniqid('', true),
            'Test Body Content',
            'All',
        ));

        $this->assertNotNull($response->circularId);
    }

    public function testGetNotificationLogSucceedsForRecipientOwner(): void
    {
        $employeeId = $this->createEmployeeFixture();
        $logId      = $this->createNotificationLogFixture('Employee', $employeeId);

        $roleId = $this->createRole(['read']);
        $userId = (new UserModel())->insert([
            'username'      => 'comm_owner_' . uniqid('', true),
            'password_hash' => password_hash(self::TEST_PASSWORD, PASSWORD_BCRYPT),
            'role_id'       => $roleId,
            'owner_type'    => 'EMPLOYEE',
            'owner_ref_id'  => $employeeId,
            'status'        => 'ACTIVE',
        ], true);

        RequestContext::setUserId($userId);
        RequestContext::setPermissionSet(['read']);

        $response = Services::notificationLogService()->getNotificationLog($logId);

        $this->assertSame($logId, $response->notificationLogId);
    }

    public function testGetNotificationLogRejectedForDifferentRecipientOwner(): void
    {
        $employeeId      = $this->createEmployeeFixture();
        $otherEmployeeId = $this->createEmployeeFixture();
        $logId           = $this->createNotificationLogFixture('Employee', $employeeId);

        $roleId = $this->createRole(['read']);
        $userId = (new UserModel())->insert([
            'username'      => 'comm_other_' . uniqid('', true),
            'password_hash' => password_hash(self::TEST_PASSWORD, PASSWORD_BCRYPT),
            'role_id'       => $roleId,
            'owner_type'    => 'EMPLOYEE',
            'owner_ref_id'  => $otherEmployeeId,
            'status'        => 'ACTIVE',
        ], true);

        RequestContext::setUserId($userId);
        RequestContext::setPermissionSet(['read']);

        $this->assertApiException(
            fn () => Services::notificationLogService()->getNotificationLog($logId),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );
    }
}
