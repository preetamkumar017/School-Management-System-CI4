<?php

declare(strict_types=1);

namespace App\Modules\Administration\DTOs;

final class SaveSchoolProfileRequest
{
    /**
     * @param array<string> $schoolLevelsOffered
     */
    public function __construct(
        public readonly string $schoolName,
        public readonly string $shortName,
        public readonly ?string $schoolCode,
        public readonly string $addressLine1,
        public readonly string $addressLine2,
        public readonly string $city,
        public readonly string $state,
        public readonly ?string $district,
        public readonly ?string $block,
        public readonly string $pinCode,
        public readonly string $country,
        public readonly string $schoolType,
        public readonly array $schoolLevelsOffered,
        public readonly string $managementType,
        public readonly string $mediumOfInstruction,
        public readonly string $residentialStatus,
        public readonly string $boardAffiliationRef,
        public readonly ?string $boardAffiliationNumber,
        public readonly ?string $recognitionNumber,
        public readonly ?string $affiliationValidityStart,
        public readonly ?string $affiliationValidityEnd,
        public readonly ?string $udiseCode,
        public readonly ?string $stateBoardCode,
        public readonly ?int $principalEmployeeId,
        public readonly ?string $principalName,
        public readonly ?string $principalEmail,
        public readonly ?string $principalPhone,
        public readonly string $schoolEmail,
        public readonly string $schoolPhone,
        public readonly ?string $emergencyContact,
        public readonly ?string $primaryLogoBase64,
        public readonly ?string $primaryLogoExtension,
        public readonly ?string $documentLogoBase64,
        public readonly ?string $documentLogoExtension,
        public readonly ?string $documentHeaderText,
        public readonly ?string $documentFooterText,
    ) {
    }
}
