<?php

declare(strict_types=1);

namespace App\Modules\Administration\Controllers;

use App\Core\BaseController;
use App\Modules\Administration\Entities\AuditLog;
use Config\Services;

/**
 * docs/design/administration/Phase-5-Controller-Design.md
 * Base path /api/v1/administration/audit-logs — read-only. No
 * POST/PATCH/DELETE route exists on this Controller at all; writes only
 * ever happen via AuditService::record() called from inside other
 * modules' Service methods, never from an HTTP request.
 */
class AuditLogController extends BaseController
{
    public function byEntity(string $entityName, int $recordId)
    {
        $rows = Services::auditService()->getHistoryFor($entityName, $recordId);

        return $this->respondSuccess(array_map($this->toResponse(...), $rows));
    }

    public function byUser(int $userId)
    {
        $from = $this->request->getGet('from');
        $to   = $this->request->getGet('to');

        $rows = Services::auditService()->getActivityFor($userId, $from, $to);

        return $this->respondSuccess(array_map($this->toResponse(...), $rows));
    }

    /**
     * @return array<string, mixed>
     */
    private function toResponse(AuditLog $log): array
    {
        return [
            'audit_log_id' => $log->audit_log_id,
            'entity_name'  => $log->entity_name,
            'record_id'    => $log->record_id,
            'action'       => $log->action,
            'performed_by' => $log->performed_by,
            'performed_at' => $log->performed_at?->toDateTimeString(),
            'old_value'    => $log->old_value,
            'new_value'    => $log->new_value,
            'reason'       => $log->reason,
            // ip_address deliberately excluded from the default response
            // (Phase 3) — a role-scoped variant would be a future addition.
        ];
    }
}
