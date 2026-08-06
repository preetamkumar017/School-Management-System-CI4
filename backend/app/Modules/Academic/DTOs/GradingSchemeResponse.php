<?php

declare(strict_types=1);

namespace App\Modules\Academic\DTOs;

use App\Modules\Academic\Entities\GradingScheme;

/**
 * docs/design/academic/Phase-3-DTO-Design.md
 */
final class GradingSchemeResponse
{
    public readonly int $gradingSchemeId;
    public readonly string $schemeName;
    public readonly string $boardType;

    /** @var array<string, string> */
    public readonly array $gradeBandJson;

    public function __construct(GradingScheme $scheme)
    {
        $this->gradingSchemeId = $scheme->grading_scheme_id;
        $this->schemeName      = $scheme->scheme_name;
        $this->boardType       = $scheme->board_type;
        $this->gradeBandJson   = $scheme->grade_band_json;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'grading_scheme_id' => $this->gradingSchemeId,
            'scheme_name'       => $this->schemeName,
            'board_type'        => $this->boardType,
            'grade_band_json'   => $this->gradeBandJson,
        ];
    }
}
