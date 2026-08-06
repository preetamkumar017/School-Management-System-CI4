<?php

declare(strict_types=1);

namespace App\Modules\Administration\Models;

use App\Modules\Administration\Entities\AuditLog;
use CodeIgniter\Model;

/**
 * docs/design/administration/Phase-2-Model-Design.md
 * Extends CodeIgniter\Model directly, not App\Core\BaseModel — write-once,
 * no soft-delete, no updated_by stamping. The only write method is the
 * inherited insert(); no update()/delete() call is ever made against this
 * table by any code in this codebase.
 */
class AuditLogModel extends Model
{
    protected $table         = 'audit_logs';
    protected $primaryKey    = 'audit_log_id';
    protected $returnType    = AuditLog::class;
    protected $useTimestamps = false;

    protected $allowedFields = [
        'entity_name',
        'record_id',
        'action',
        'performed_by',
        'performed_at',
        'old_value',
        'new_value',
        'ip_address',
        'reason',
    ];

    protected $beforeInsert = ['encodeSnapshots'];

    /**
     * old_value/new_value arrive as plain PHP arrays (AuditService::record's
     * snapshots) — the Query Builder binds a raw array as a tuple, not
     * JSON, so both need encoding here before the insert.
     */
    protected function encodeSnapshots(array $eventData): array
    {
        foreach (['old_value', 'new_value'] as $field) {
            if (isset($eventData['data'][$field]) && is_array($eventData['data'][$field])) {
                $eventData['data'][$field] = json_encode($eventData['data'][$field]);
            }
        }

        return $eventData;
    }

    /**
     * @return list<AuditLog>
     */
    public function findByEntity(string $entityName, int $recordId): array
    {
        return $this->where('entity_name', $entityName)
            ->where('record_id', $recordId)
            ->orderBy('performed_at', 'DESC')
            ->findAll();
    }

    /**
     * @return list<AuditLog>
     */
    public function findByPerformedBy(int $userId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $builder = $this->where('performed_by', $userId);

        if ($fromDate !== null) {
            $builder = $builder->where('performed_at >=', $fromDate);
        }

        if ($toDate !== null) {
            $builder = $builder->where('performed_at <=', $toDate);
        }

        return $builder->orderBy('performed_at', 'DESC')->findAll();
    }
}
