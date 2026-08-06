<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Http\RequestContext;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Model;

/**
 * Every product Model extends this (Company Development Standard §4.4) —
 * a CI4 Model acting as its entity's repository. Populates the common
 * audit columns automatically; callers never set created_by/updated_by/
 * is_deleted/deleted_by themselves.
 *
 * Not for AuditLogModel or RefreshTokenModel — neither follows the common
 * audit-column baseline (see docs/design/administration/Phase-1); both
 * extend CodeIgniter\Model directly instead.
 */
abstract class BaseModel extends Model
{
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';

    protected $beforeInsert = ['stampCreatedBy'];
    protected $beforeUpdate = ['stampUpdatedBy'];

    protected function stampCreatedBy(array $eventData): array
    {
        $eventData['data']['created_by'] ??= RequestContext::userId();
        $eventData['data']['updated_by'] ??= RequestContext::userId();

        return $eventData;
    }

    protected function stampUpdatedBy(array $eventData): array
    {
        $eventData['data']['updated_by'] = RequestContext::userId();

        return $eventData;
    }

    /**
     * CI4's own soft-delete only maintains $deletedField. This project's
     * common-columns baseline also has is_deleted/deleted_by (Company
     * Development Standard §4.4), which the framework has no hook for —
     * overriding doDelete's soft-delete branch is the only place both the
     * timestamp and these two columns can be set in the same query.
     */
    protected function doDelete($id = null, bool $purge = false)
    {
        if (! $this->useSoftDeletes || $purge) {
            return parent::doDelete($id, $purge);
        }

        $builder = $this->builder();

        if (is_array($id) && $id !== []) {
            $builder = $builder->whereIn($this->primaryKey, $id);
        }

        if ($builder->getCompiledQBWhere() === []) {
            throw new DatabaseException(
                'Deletes are not allowed unless they contain a "where" or "like" clause.',
            );
        }

        $builder->where($this->deletedField);

        $set = [
            $this->deletedField => $this->setDate(),
            'is_deleted'        => 1,
            'deleted_by'        => RequestContext::userId(),
        ];

        if ($this->useTimestamps && $this->updatedField !== '') {
            $set[$this->updatedField] = $this->setDate();
        }

        return $builder->update($set);
    }
}
