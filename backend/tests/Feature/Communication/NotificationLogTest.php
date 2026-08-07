<?php

declare(strict_types=1);

namespace Tests\Feature\Communication;

use Tests\Support\Communication\CommunicationTestCase;

/**
 * @internal
 */
final class NotificationLogTest extends CommunicationTestCase
{
    public function testCreateValidatesRecipientAndDefaultsToQueued(): void
    {
        $user    = $this->createUser();
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $response = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/communication/notification-logs', [
            'recipient_type' => 'User',
            'recipient_ref_id' => $user['id'],
            'channel'       => 'Email',
            'trigger_event' => 'Test event',
        ]);

        $response->assertStatus(201);
        $this->assertSame('Queued', $this->decode($response)['data']['status']);
    }

    /**
     * BR-COM-004: every failed delivery is logged with a reason.
     */
    public function testMarkFailedRequiresAndStoresReason(): void
    {
        $user               = $this->createUser();
        $tokens             = $this->loginAs($user['username']);
        $headers            = $this->authHeaders($tokens['access_token']);
        $notificationLogId  = $this->createNotificationLogFixture('User', $user['id']);

        $response = $this->withHeaders($headers)->withBodyFormat('json')->post("api/v1/communication/notification-logs/{$notificationLogId}/fail", [
            'failure_reason' => 'Invalid phone number on file.',
        ]);

        $response->assertStatus(200);
        $body = $this->decode($response)['data'];
        $this->assertSame('Failed', $body['status']);
        $this->assertSame('Invalid phone number on file.', $body['failure_reason']);
    }

    public function testDispatchThenDeliverTransitions(): void
    {
        $user               = $this->createUser();
        $tokens             = $this->loginAs($user['username']);
        $headers            = $this->authHeaders($tokens['access_token']);
        $notificationLogId  = $this->createNotificationLogFixture('User', $user['id']);

        $this->withHeaders($headers)->post("api/v1/communication/notification-logs/{$notificationLogId}/dispatch")->assertStatus(200);
        $deliver = $this->withHeaders($headers)->post("api/v1/communication/notification-logs/{$notificationLogId}/deliver");
        $deliver->assertStatus(200);
        $this->assertSame('Delivered', $this->decode($deliver)['data']['status']);
    }
}
