<?php

declare(strict_types=1);

namespace App\Modules\Administration\Controllers;

use App\Core\BaseController;
use App\Modules\Administration\Entities\AuditLog;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/administration/Phase-5-Controller-Design.md
 * Base path /api/v1/administration/audit-logs — read-only. No
 * POST/PATCH/DELETE route exists on this Controller at all; writes only
 * ever happen via AuditService::record() called from inside other
 * modules' Service methods, never from an HTTP request.
 */
#[OA\Tag(name: 'Audit Logs')]
class AuditLogController extends BaseController
{
    #[OA\Get(
        path: '/administration/audit-logs/by-entity/{entityName}/{recordId}',
        tags: ['Audit Logs'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'entityName', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'Role'),
            new OA\Parameter(name: 'recordId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Full change history for one record, newest first.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/AuditLogResponse')),
            ),
        ],
    )]
    public function byEntity(string $entityName, int $recordId)
    {
        $rows = Services::auditService()->getHistoryFor($entityName, $recordId);

        return $this->respondSuccess(array_map($this->toResponse(...), $rows));
    }

    #[OA\Get(
        path: '/administration/audit-logs/by-user/{userId}',
        tags: ['Audit Logs'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date-time')),
            new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date-time')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Everything this user did, newest first.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/AuditLogResponse')),
            ),
        ],
    )]
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
