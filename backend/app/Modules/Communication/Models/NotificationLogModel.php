<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use App\Core\BaseModel;
use App\Modules\Communication\Entities\NotificationLog;

/**
 * docs/design/communication/Phase-2-Model-DTO-Design.md
 */
class NotificationLogModel extends BaseModel
{
    protected $table          = 'notification_logs';
    protected $primaryKey     = 'notification_log_id';
    protected $returnType     = NotificationLog::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'recipient_type',
        'recipient_ref_id',
        'channel',
        'trigger_event',
        'message_body',
        'status',
        'dispatched_at',
        'failure_reason',
        'created_by',
        'updated_by',
    ];

    /**
     * @return list<NotificationLog>
     */
    public function findByRecipient(string $recipientType, int $recipientRefId): array
    {
        return $this->where('recipient_type', $recipientType)
            ->where('recipient_ref_id', $recipientRefId)
            ->findAll();
    }
}
