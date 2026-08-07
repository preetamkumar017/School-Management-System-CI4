<?php

declare(strict_types=1);

namespace Tests\Feature\Communication;

use App\Modules\Communication\Entities\NotificationLog;
use App\Modules\Communication\Models\NotificationLogModel;
use App\Modules\Communication\Services\NotificationLogService;
use App\Modules\Sis\Models\GuardianModel;
use App\Modules\Sis\Models\StudentGuardianLinkModel;
use Config\Services;
use Tests\Support\Communication\CommunicationTestCase;
use Tests\Support\Communication\FakeEmailGateway;
use Tests\Support\Communication\FakeSmsGateway;

/**
 * docs/ADR/ADR-021-communication-sms-email-gateway.md — real dispatch,
 * deterministic and offline. MSG91's real API is never called from a
 * test; a fake SmsGatewayInterface/EmailGatewayInterface is bound via
 * Services::injectMock() instead.
 *
 * @internal
 */
final class NotificationDispatchTest extends CommunicationTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        Services::reset(true);
    }

    private function bindGateways(?FakeSmsGateway $sms = null, ?FakeEmailGateway $email = null): NotificationLogService
    {
        $sms   ??= new FakeSmsGateway();
        $email ??= new FakeEmailGateway();

        $service = new NotificationLogService(
            new NotificationLogModel(),
            Services::auditService(),
            new GuardianModel(),
            new StudentGuardianLinkModel(),
            $sms,
            $email,
        );

        Services::injectMock('notificationLogService', $service);

        return $service;
    }

    public function testDispatchToGuardianCallsSmsGatewayWithGuardianContactAndMarksDispatched(): void
    {
        $sms       = new FakeSmsGateway();
        $this->bindGateways($sms);

        $user               = $this->createUser();
        $tokens              = $this->loginAs($user['username']);
        $headers             = $this->authHeaders($tokens['access_token']);
        $guardianId          = $this->createGuardianFixture('9876543210');
        $notificationLogId   = $this->createNotificationLogFixture('Guardian', $guardianId, 'SMS');

        $response = $this->withHeaders($headers)->post("api/v1/communication/notification-logs/{$notificationLogId}/dispatch");

        $response->assertStatus(200);
        $body = $this->decode($response)['data'];
        $this->assertSame('Dispatched', $body['status']);
        $this->assertNotNull($body['dispatched_at']);

        $this->assertCount(1, $sms->calls);
        $this->assertSame('9876543210', $sms->calls[0]['mobile']);
    }

    public function testDispatchToStudentResolvesPrimaryContactGuardian(): void
    {
        $sms = new FakeSmsGateway();
        $this->bindGateways($sms);

        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $studentId          = $this->createStudentFixture();
        $secondaryGuardianId = $this->createGuardianFixture('1111111111');
        $primaryGuardianId   = $this->createGuardianFixture('2222222222');

        $this->linkGuardianToStudent($studentId, $secondaryGuardianId, false);
        $this->linkGuardianToStudent($studentId, $primaryGuardianId, true);

        $notificationLogId = $this->createNotificationLogFixture('Student', $studentId, 'SMS');

        $response = $this->withHeaders($headers)->post("api/v1/communication/notification-logs/{$notificationLogId}/dispatch");

        $response->assertStatus(200);
        $this->assertSame('Dispatched', $this->decode($response)['data']['status']);

        $this->assertCount(1, $sms->calls);
        // Must use the primary-contact guardian's number, not the
        // secondary/first-linked one.
        $this->assertSame('2222222222', $sms->calls[0]['mobile']);
    }

    public function testDispatchToEmployeeFailsWithDocumentedReasonWithoutThrowing(): void
    {
        $this->bindGateways();

        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $employeeId = $this->createEmployeeFixture();

        $notificationLogId = $this->createNotificationLogFixture('Employee', $employeeId, 'Email');

        $response = $this->withHeaders($headers)->post("api/v1/communication/notification-logs/{$notificationLogId}/dispatch");

        $response->assertStatus(200);
        $body = $this->decode($response)['data'];
        $this->assertSame('Failed', $body['status']);
        $this->assertStringContainsString('No contact information available for recipient_type Employee', $body['failure_reason']);
    }

    public function testDispatchToUserFailsWithDocumentedReasonWithoutThrowing(): void
    {
        $this->bindGateways();

        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $notificationLogId = $this->createNotificationLogFixture('User', $user['id'], 'Email');

        $response = $this->withHeaders($headers)->post("api/v1/communication/notification-logs/{$notificationLogId}/dispatch");

        $response->assertStatus(200);
        $body = $this->decode($response)['data'];
        $this->assertSame('Failed', $body['status']);
        $this->assertStringContainsString('No contact information available for recipient_type User', $body['failure_reason']);
    }

    public function testDispatchWhenGatewayCallFailsMarksFailedWithCapturedError(): void
    {
        $sms = new FakeSmsGateway('MSG91 SMS send failed (HTTP 401): invalid authkey');
        $this->bindGateways($sms);

        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $guardianId = $this->createGuardianFixture('9876543210');

        $notificationLogId = $this->createNotificationLogFixture('Guardian', $guardianId, 'SMS');

        $response = $this->withHeaders($headers)->post("api/v1/communication/notification-logs/{$notificationLogId}/dispatch");

        $response->assertStatus(200);
        $body = $this->decode($response)['data'];
        $this->assertSame('Failed', $body['status']);
        $this->assertSame('MSG91 SMS send failed (HTTP 401): invalid authkey', $body['failure_reason']);
    }

    public function testDispatchOnPushChannelFailsWithClearReason(): void
    {
        $this->bindGateways();

        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $guardianId = $this->createGuardianFixture('9876543210');

        $notificationLogId = $this->createNotificationLogFixture('Guardian', $guardianId, NotificationLog::CHANNEL_PUSH);

        $response = $this->withHeaders($headers)->post("api/v1/communication/notification-logs/{$notificationLogId}/dispatch");

        $response->assertStatus(200);
        $body = $this->decode($response)['data'];
        $this->assertSame('Failed', $body['status']);
        $this->assertStringContainsString('No push notification provider', $body['failure_reason']);
    }

    public function testCreatePersistsMessageBody(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $response = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/communication/notification-logs', [
            'recipient_type'   => 'User',
            'recipient_ref_id' => $user['id'],
            'channel'          => 'Email',
            'trigger_event'    => 'Test event',
            'message_body'     => 'A real message body.',
        ]);

        $response->assertStatus(201);
        $this->assertSame('A real message body.', $this->decode($response)['data']['message_body']);
    }
}
