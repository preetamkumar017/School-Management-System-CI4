<?php

declare(strict_types=1);

namespace App\Modules\Sis\DTOs;

use App\Modules\Sis\Entities\Guardian;

/**
 * docs/design/sis/Phase-4.4-DTO-Design.md
 */
final class GuardianResponse
{
    public readonly int $guardianId;
    public readonly string $fullName;
    public readonly string $relationship;
    public readonly string $mobileNumber;
    public readonly ?string $email;

    public function __construct(Guardian $guardian)
    {
        $this->guardianId   = $guardian->guardian_id;
        $this->fullName     = $guardian->full_name;
        $this->relationship = $guardian->relationship;
        $this->mobileNumber = $guardian->mobile_number;
        $this->email        = $guardian->email;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'guardian_id'  => $this->guardianId,
            'full_name'    => $this->fullName,
            'relationship' => $this->relationship,
            'mobile_number' => $this->mobileNumber,
            'email'        => $this->email,
        ];
    }
}
