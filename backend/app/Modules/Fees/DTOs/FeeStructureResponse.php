<?php

declare(strict_types=1);

namespace App\Modules\Fees\DTOs;

use App\Modules\Fees\Entities\FeeStructure;

/**
 * docs/design/fees/Phase-2-Model-DTO-Design.md
 */
final class FeeStructureResponse
{
    public readonly int $feeStructureId;
    public readonly int $classId;
    public readonly int $feeHeadId;
    public readonly int $academicSessionId;
    public readonly ?int $routeId;
    public readonly string $category;
    public readonly float $amount;

    public function __construct(FeeStructure $feeStructure)
    {
        $this->feeStructureId    = $feeStructure->fee_structure_id;
        $this->classId           = $feeStructure->class_id;
        $this->feeHeadId         = $feeStructure->fee_head_id;
        $this->academicSessionId = $feeStructure->academic_session_id;
        $this->routeId           = $feeStructure->route_id;
        $this->category          = $feeStructure->category;
        $this->amount            = $feeStructure->amount;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fee_structure_id'    => $this->feeStructureId,
            'class_id'            => $this->classId,
            'fee_head_id'         => $this->feeHeadId,
            'academic_session_id' => $this->academicSessionId,
            'route_id'            => $this->routeId,
            'category'            => $this->category,
            'amount'              => $this->amount,
        ];
    }
}
