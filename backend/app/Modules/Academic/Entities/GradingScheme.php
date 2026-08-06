<?php

declare(strict_types=1);

namespace App\Modules\Academic\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/academic/Phase-1-Domain-Model.md — ENT-ACAD-005.
 *
 * @property int|null            $grading_scheme_id
 * @property string              $scheme_name
 * @property string              $board_type
 * @property array<string, string> $grade_band_json
 */
class GradingScheme extends BaseEntity
{
    public const BOARD_CBSE = 'CBSE';
    public const BOARD_ICSE = 'ICSE';
    public const BOARD_STATE_BOARD = 'STATE_BOARD';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'grading_scheme_id' => 'integer',
            'grade_band_json'   => 'json-array',
        ]);

        parent::__construct($data);
    }
}
