<?php

declare(strict_types=1);

namespace App\Modules\Sis\Mappers;

use App\Modules\Sis\DTOs\CreateGuardianRequest;
use App\Modules\Sis\DTOs\GuardianResponse;
use App\Modules\Sis\DTOs\UpdateGuardianRequest;
use App\Modules\Sis\Entities\Guardian;

/**
 * docs/design/sis/Phase-4.5-Mapper-Design.md
 */
class GuardianMapper
{
    public function toEntity(CreateGuardianRequest $request): Guardian
    {
        return new Guardian([
            'full_name'     => $request->fullName,
            'relationship'  => $request->relationship,
            'mobile_number' => $request->mobileNumber,
            'email'         => $request->email,
        ]);
    }

    public function updateEntity(UpdateGuardianRequest $request, Guardian $target): void
    {
        $target->full_name     = $request->fullName;
        $target->relationship  = $request->relationship;
        $target->mobile_number = $request->mobileNumber;
        $target->email         = $request->email;
    }

    public function toResponse(Guardian $guardian): GuardianResponse
    {
        return new GuardianResponse($guardian);
    }
}
