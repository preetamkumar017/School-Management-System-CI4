<?php

declare(strict_types=1);

namespace App\Modules\Communication\DTOs;

use App\Modules\Communication\Entities\NotificationLog;

/**
 * docs/design/communication/Phase-2-Model-DTO-Design.md
 */
final class NotificationLogResponse
{
    public readonly int $notificationLogId;
    public readonly string $recipientType;
    public readonly int $recipientRefId;
    public readonly string $channel;
    public readonly string $triggerEvent;
    public readonly ?string $messageBody;
    public readonly string $status;
    public readonly ?string $dispatchedAt;
    public readonly ?string $failureReason;

    public function __construct(NotificationLog $notificationLog)
    {
        $this->notificationLogId = $notificationLog->notification_log_id;
        $this->recipientType     = $notificationLog->recipient_type;
        $this->recipientRefId    = $notificationLog->recipient_ref_id;
        $this->channel           = $notificationLog->channel;
        $this->triggerEvent      = $notificationLog->trigger_event;
        $this->messageBody       = $notificationLog->message_body;
        $this->status            = $notificationLog->status;
        $this->dispatchedAt      = $notificationLog->dispatched_at;
        $this->failureReason     = $notificationLog->failure_reason;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'notification_log_id' => $this->notificationLogId,
            'recipient_type'      => $this->recipientType,
            'recipient_ref_id'    => $this->recipientRefId,
            'channel'             => $this->channel,
            'trigger_event'       => $this->triggerEvent,
            'message_body'        => $this->messageBody,
            'status'              => $this->status,
            'dispatched_at'       => $this->dispatchedAt,
            'failure_reason'      => $this->failureReason,
        ];
    }
}
