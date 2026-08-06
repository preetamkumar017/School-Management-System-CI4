<?php

declare(strict_types=1);

namespace App\Modules\Administration\Entities;

use CodeIgniter\Entity\Entity;

/**
 * docs/design/administration/Phase-1-Domain-Model.md — ENT-SYS-004.
 * Extends CodeIgniter\Entity\Entity directly, not App\Core\BaseEntity —
 * write-once, no updated_at/deleted_at/soft-delete assumptions apply.
 *
 * @property int|null    $audit_log_id
 * @property string      $entity_name
 * @property int         $record_id
 * @property string      $action
 * @property int         $performed_by
 * @property string      $performed_at
 * @property array|null  $old_value
 * @property array|null  $new_value
 * @property string|null $ip_address
 * @property string|null $reason
 */
class AuditLog extends Entity
{
    public const ACTION_CREATE   = 'CREATE';
    public const ACTION_UPDATE   = 'UPDATE';
    public const ACTION_DELETE   = 'DELETE';
    public const ACTION_APPROVE  = 'APPROVE';
    public const ACTION_REJECT   = 'REJECT';
    public const ACTION_OVERRIDE = 'OVERRIDE';

    protected $dates = ['performed_at'];

    protected $casts = [
        'audit_log_id' => 'integer',
        'record_id'    => 'integer',
        'performed_by' => 'integer',
        'old_value'    => '?json-array',
        'new_value'    => '?json-array',
    ];
}
