<?php

declare(strict_types=1);

namespace App\Modules\Administration\Services;

use App\Core\Exceptions\ValidationException;
use App\Core\Http\RequestContext;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Models\AuditLogModel;
use CodeIgniter\I18n\Time;

/**
 * docs/design/administration/Phase-4-Service-Design.md
 *
 * The single write path every other module's Service layer calls —
 * never AuditLogModel::insert() directly from outside Administration.
 */
class AuditService
{
    public function __construct(private readonly AuditLogModel $auditLogModel)
    {
    }

    /**
     * @param array<string, mixed>|null $oldValue
     * @param array<string, mixed>|null $newValue
     */
    public function record(
        string $entityName,
        int $recordId,
        string $action,
        ?array $oldValue = null,
        ?array $newValue = null,
        ?string $reason = null,
    ): void {
        if ($action === AuditLog::ACTION_OVERRIDE && ($reason === null || trim($reason) === '')) {
            throw new ValidationException(
                ['reason' => 'A reason is required when overriding.'],
                'A reason is required when the action is OVERRIDE.',
            );
        }

        $this->auditLogModel->insert([
            'entity_name'  => $entityName,
            'record_id'    => $recordId,
            'action'       => $action,
            'performed_by' => RequestContext::userId(),
            'performed_at' => Time::now()->toDateTimeString(),
            'old_value'    => $oldValue,
            'new_value'    => $newValue,
            'ip_address'   => service('request')->getIPAddress(),
            'reason'       => $reason,
        ]);
    }

    /**
     * @return list<AuditLog>
     */
    public function getHistoryFor(string $entityName, int $recordId): array
    {
        return $this->auditLogModel->findByEntity($entityName, $recordId);
    }

    /**
     * @return list<AuditLog>
     */
    public function getActivityFor(int $userId, ?string $fromDate = null, ?string $toDate = null): array
    {
        return $this->auditLogModel->findByPerformedBy($userId, $fromDate, $toDate);
    }
}
