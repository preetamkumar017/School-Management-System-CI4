<?php

declare(strict_types=1);

namespace App\Modules\Transport\Models;

use App\Core\BaseModel;
use App\Modules\Transport\Entities\Route;

/**
 * docs/design/transport/Phase-2-Model-DTO-Design.md
 */
class RouteModel extends BaseModel
{
    protected $table          = 'routes';
    protected $primaryKey     = 'route_id';
    protected $returnType     = Route::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'route_name',
        'stops_json',
        'capacity',
        'vehicle_id',
        'created_by',
        'updated_by',
    ];

    protected $beforeInsert = ['stampCreatedBy', 'encodeStopsJson'];
    protected $beforeUpdate = ['stampUpdatedBy', 'encodeStopsJson'];

    /**
     * Same reasoning as GradingSchemeModel::encodeGradeBandJson — the
     * Query Builder binds a raw PHP array as a tuple, not JSON, unless
     * encoded first.
     */
    protected function encodeStopsJson(array $eventData): array
    {
        if (isset($eventData['data']['stops_json']) && is_array($eventData['data']['stops_json'])) {
            $eventData['data']['stops_json'] = json_encode($eventData['data']['stops_json']);
        }

        return $eventData;
    }

    public function findByName(string $value): ?Route
    {
        return $this->where('route_name', $value)->first();
    }

    public function existsByName(string $value): bool
    {
        return $this->where('route_name', $value)->countAllResults() > 0;
    }

    public function existsByNameExceptId(string $value, int $id): bool
    {
        return $this->where('route_name', $value)->where('route_id !=', $id)->countAllResults() > 0;
    }

    /**
     * BR-TRN-001/002 (ADR-009 §9/§10) — a genuine row lock, the same
     * raw-SQL `SELECT ... FOR UPDATE` shape
     * `SeatAllocationModel::incrementSeatsFilled` established in Stage 4.
     * Caller is responsible for wrapping this in a transaction.
     */
    public function findForUpdate(int $routeId): ?Route
    {
        $row = $this->db->query(
            'SELECT route_id, route_name, stops_json, capacity, vehicle_id FROM routes WHERE route_id = ? FOR UPDATE',
            [$routeId],
        )->getRowArray();

        // injectRawData(), not `new Route($row)` — the constructor runs
        // raw DB values through each field's "set" cast too (via fill()'s
        // __set()), which for a JSON column double-encodes an
        // already-encoded string. injectRawData() stores the row as-is,
        // matching how CI4's own Model::find() hydrates entities.
        return $row === null ? null : (new Route())->injectRawData($row);
    }
}
