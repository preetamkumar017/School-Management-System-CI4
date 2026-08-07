<?php

declare(strict_types=1);

namespace App\Modules\Communication\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Communication\DTOs\CreateNotificationLogRequest;
use App\Modules\Communication\DTOs\MarkNotificationFailedRequest;
use App\Modules\Communication\Entities\NotificationLog;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/communication/Phase-3-Service-Controller-Design.md
 * Base path /api/v1/communication/notification-logs
 */
#[OA\Tag(name: 'Notification Logs')]
class NotificationLogController extends BaseController
{
    private const VALID_RECIPIENT_TYPES = [
        NotificationLog::RECIPIENT_GUARDIAN,
        NotificationLog::RECIPIENT_EMPLOYEE,
        NotificationLog::RECIPIENT_USER,
    ];

    private const VALID_CHANNELS = [
        NotificationLog::CHANNEL_SMS,
        NotificationLog::CHANNEL_EMAIL,
        NotificationLog::CHANNEL_PUSH,
    ];

    #[OA\Post(
        path: '/communication/notification-logs',
        tags: ['Notification Logs'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/NotificationLogCreateRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/NotificationLogResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];

        $recipientType  = (string) ($body['recipient_type'] ?? '');
        $recipientRefId = (int) ($body['recipient_ref_id'] ?? 0);
        $channel        = (string) ($body['channel'] ?? '');
        $triggerEvent   = (string) ($body['trigger_event'] ?? '');

        $fields = [];

        if (! in_array($recipientType, self::VALID_RECIPIENT_TYPES, true)) {
            $fields['recipient_type'] = 'recipient_type must be one of Guardian, Employee, User.';
        }

        if ($recipientRefId <= 0) {
            $fields['recipient_ref_id'] = 'recipient_ref_id is required.';
        }

        if (! in_array($channel, self::VALID_CHANNELS, true)) {
            $fields['channel'] = 'channel must be one of SMS, Email, Push.';
        }

        if ($triggerEvent === '') {
            $fields['trigger_event'] = 'trigger_event is required.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $messageBody = isset($body['message_body']) ? (string) $body['message_body'] : null;

        $response = Services::notificationLogService()->create(
            new CreateNotificationLogRequest($recipientType, $recipientRefId, $channel, $triggerEvent, $messageBody),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Post(
        path: '/communication/notification-logs/{id}/dispatch',
        tags: ['Notification Logs'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Real dispatch attempted via the configured gateway (ADR-021); marks Dispatched on success or Failed with a reason on failure.', content: new OA\JsonContent(ref: '#/components/schemas/NotificationLogResponse'))],
    )]
    public function dispatch(int $id)
    {
        return $this->respondSuccess(Services::notificationLogService()->dispatch($id)->toArray());
    }

    #[OA\Post(
        path: '/communication/notification-logs/{id}/deliver',
        tags: ['Notification Logs'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Marked Delivered.', content: new OA\JsonContent(ref: '#/components/schemas/NotificationLogResponse'))],
    )]
    public function deliver(int $id)
    {
        return $this->respondSuccess(Services::notificationLogService()->markDelivered($id)->toArray());
    }

    #[OA\Post(
        path: '/communication/notification-logs/{id}/fail',
        tags: ['Notification Logs'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/NotificationLogFailRequest')),
        responses: [new OA\Response(response: 200, description: 'Marked Failed (BR-COM-004).', content: new OA\JsonContent(ref: '#/components/schemas/NotificationLogResponse'))],
    )]
    public function fail(int $id)
    {
        $body          = $this->request->getJSON(true) ?? [];
        $failureReason = (string) ($body['failure_reason'] ?? '');

        if ($failureReason === '') {
            throw new ValidationException(['failure_reason' => 'failure_reason is required (BR-COM-004).']);
        }

        return $this->respondSuccess(Services::notificationLogService()->markFailed($id, new MarkNotificationFailedRequest($failureReason))->toArray());
    }

    #[OA\Get(
        path: '/communication/notification-logs/{id}',
        tags: ['Notification Logs'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/NotificationLogResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::notificationLogService()->getNotificationLog($id)->toArray());
    }

    #[OA\Get(
        path: '/communication/notification-logs',
        tags: ['Notification Logs'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'recipient_type', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['Guardian', 'Employee', 'User'])),
            new OA\Parameter(name: 'recipient_ref_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/NotificationLogResponse')),
            ),
        ],
    )]
    public function index()
    {
        $recipientType  = (string) ($this->request->getGet('recipient_type') ?? '');
        $recipientRefId = (int) ($this->request->getGet('recipient_ref_id') ?? 0);

        $fields = [];

        if (! in_array($recipientType, self::VALID_RECIPIENT_TYPES, true)) {
            $fields['recipient_type'] = 'recipient_type query parameter must be one of Guardian, Employee, User.';
        }

        if ($recipientRefId <= 0) {
            $fields['recipient_ref_id'] = 'recipient_ref_id query parameter is required.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $responses = Services::notificationLogService()->listByRecipient($recipientType, $recipientRefId);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }
}
