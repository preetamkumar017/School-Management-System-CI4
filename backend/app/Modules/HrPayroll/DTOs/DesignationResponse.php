<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\DTOs;

use App\Modules\HrPayroll\Entities\Designation;

/**
 * docs/design/hr-payroll/Phase-2-Model-DTO-Design.md
 */
final class DesignationResponse
{
    public readonly int $designationId;
    public readonly string $designationName;

    public function __construct(Designation $designation)
    {
        $this->designationId   = $designation->designation_id;
        $this->designationName = $designation->designation_name;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'designation_id'   => $this->designationId,
            'designation_name' => $this->designationName,
        ];
    }
}
