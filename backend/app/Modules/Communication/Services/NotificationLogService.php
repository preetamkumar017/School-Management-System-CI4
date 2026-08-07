<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\Communication\DTOs\CreateNotificationLogRequest;
use App\Modules\Communication\DTOs\MarkNotificationFailedRequest;
use App\Modules\Communication\DTOs\NotificationLogResponse;
use App\Modules\Communication\Entities\NotificationLog;
use App\Modules\Communication\Models\NotificationLogModel;
use CodeIgniter\I18n\Time;
use Config\Services as AppServices;

/**
 * docs/design/communication/Phase-3-Service-Controller-Design.md
 * A log/record-keeping Service, not a live dispatcher — no gateway
 * integration exists (ADR-010 §2).
 */
class NotificationLogService
{
    public function __construct(
        private readonly NotificationLogModel $notificationLogModel,
        private readonly AuditService $auditService,
    ) {
    }

    public function create(CreateNotificationLogRequest $request): NotificationLogResponse
    {
        $this->validateRecipient($request->recipientType, $request->recipientRefId);

        $id = $this->notificationLogModel->insert([
            'recipient_type'   => $request->recipientType,
            'recipient_ref_id' => $request->recipientRefId,
            'channel'          => $request->channel,
            'trigger_event'    => $request->triggerEvent,
            'status'           => NotificationLog::STATUS_QUEUED,
        ], true);

        $notificationLog = $this->notificationLogModel->find($id);

        $this->auditService->record('NotificationLog', $id, AuditLog::ACTION_CREATE, null, $notificationLog->toRawArray());

        return new NotificationLogResponse($notificationLog);
    }

    public function markDispatched(int $id): NotificationLogResponse
    {
        return $this->changeStatus($id, NotificationLog::STATUS_DISPATCHED, [
            'dispatched_at' => Time::now()->toDateTimeString(),
        ]);
    }

    public function markDelivered(int $id): NotificationLogResponse
    {
        return $this->changeStatus($id, NotificationLog::STATUS_DELIVERED);
    }

    /**
     * BR-COM-004: every failed delivery is logged with a reason.
     */
    public function markFailed(int $id, MarkNotificationFailedRequest $request): NotificationLogResponse
    {
        $before = $this->requireNotificationLog($id);

        $this->notificationLogModel->update($id, [
            'status'         => NotificationLog::STATUS_FAILED,
            'failure_reason' => $request->failureReason,
        ]);

        $after = $this->notificationLogModel->find($id);

        $this->auditService->record(
            'NotificationLog',
            $id,
            AuditLog::ACTION_OVERRIDE,
            $before->toRawArray(),
            $after->toRawArray(),
            $request->failureReason,
        );

        return new NotificationLogResponse($after);
    }

    public function getNotificationLog(int $id): NotificationLogResponse
    {
        return new NotificationLogResponse($this->requireNotificationLog($id));
    }

    /**
     * @return list<NotificationLogResponse>
     */
    public function listByRecipient(string $recipientType, int $recipientRefId): array
    {
        return array_map(
            static fn (NotificationLog $notificationLog): NotificationLogResponse => new NotificationLogResponse($notificationLog),
            $this->notificationLogModel->findByRecipient($recipientType, $recipientRefId),
        );
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function changeStatus(int $id, string $status, array $extra = []): NotificationLogResponse
    {
        $before = $this->requireNotificationLog($id);

        $this->notificationLogModel->update($id, array_merge(['status' => $status], $extra));
        $after = $this->notificationLogModel->find($id);

        $this->auditService->record('NotificationLog', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new NotificationLogResponse($after);
    }

    private function validateRecipient(string $recipientType, int $recipientRefId): void
    {
        match ($recipientType) {
            NotificationLog::RECIPIENT_GUARDIAN => AppServices::guardianService()->getGuardian($recipientRefId),
            NotificationLog::RECIPIENT_EMPLOYEE => AppServices::employeeService()->getEmployee($recipientRefId),
            NotificationLog::RECIPIENT_USER     => AppServices::userService()->getUser($recipientRefId),
            NotificationLog::RECIPIENT_STUDENT  => AppServices::studentService()->getStudent($recipientRefId),
            default                             => throw new BusinessRuleException('INVALID_RECIPIENT_TYPE', 'recipient_type must be one of Guardian, Employee, User, Student.'),
        };
    }

    private function requireNotificationLog(int $id): NotificationLog
    {
        $notificationLog = $this->notificationLogModel->find($id);

        if ($notificationLog === null) {
            throw new BusinessRuleException('NOTIFICATION_LOG_NOT_FOUND', 'Notification log not found.');
        }

        return $notificationLog;
    }
}
