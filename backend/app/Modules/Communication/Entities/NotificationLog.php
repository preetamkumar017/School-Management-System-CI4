<?php

declare(strict_types=1);

namespace App\Modules\Communication\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/communication/Phase-1-Domain-Model.md — ENT-COM-002.
 * Includes the decided additive failure_reason column (ADR-010 §2) and
 * the additive Student recipient_type value (ADR-017 §7 — a Library
 * reservation holder can be a Student, which no prior call site needed).
 *
 * @property int|null $notification_log_id
 * @property string   $recipient_type
 * @property int      $recipient_ref_id
 * @property string   $channel
 * @property string   $trigger_event
 * @property string|null $message_body
 * @property string   $status
 * @property string|null $dispatched_at
 * @property string|null $failure_reason
 */
class NotificationLog extends BaseEntity
{
    public const RECIPIENT_GUARDIAN = 'Guardian';
    public const RECIPIENT_EMPLOYEE = 'Employee';
    public const RECIPIENT_USER     = 'User';
    public const RECIPIENT_STUDENT  = 'Student';

    public const CHANNEL_SMS   = 'SMS';
    public const CHANNEL_EMAIL = 'Email';
    public const CHANNEL_PUSH  = 'Push';

    public const STATUS_QUEUED     = 'Queued';
    public const STATUS_DISPATCHED = 'Dispatched';
    public const STATUS_DELIVERED  = 'Delivered';
    public const STATUS_FAILED     = 'Failed';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'notification_log_id' => 'integer',
            'recipient_ref_id'    => 'integer',
        ]);

        parent::__construct($data);
    }
}
