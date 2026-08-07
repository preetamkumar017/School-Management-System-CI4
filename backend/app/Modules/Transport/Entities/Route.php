<?php

declare(strict_types=1);

namespace App\Modules\Transport\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/transport/Phase-1-Domain-Model.md — ENT-TRN-001. Includes
 * the decided additive vehicle_id column (ADR-009 §8).
 *
 * @property int|null       $route_id
 * @property string         $route_name
 * @property array<int,string> $stops_json
 * @property int            $capacity
 * @property int|null       $vehicle_id
 */
class Route extends BaseEntity
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'route_id'   => 'integer',
            'stops_json' => 'json-array',
            'capacity'   => 'integer',
            'vehicle_id' => '?integer',
        ]);

        parent::__construct($data);
    }
}
