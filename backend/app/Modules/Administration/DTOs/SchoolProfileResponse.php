<?php

declare(strict_types=1);

namespace App\Modules\Administration\DTOs;

use App\Modules\Administration\Entities\SchoolProfile;

final class SchoolProfileResponse
{
    public readonly ?int $schoolId;
    public readonly string $schoolName;
    public readonly string $shortName;
    public readonly ?string $schoolCode;
    public readonly string $addressLine1;
    public readonly string $addressLine2;
    public readonly string $city;
    public readonly string $state;
    public readonly ?string $district;
    public readonly ?string $block;
    public readonly string $pinCode;
    public readonly string $country;
    public readonly string $schoolType;
    /** @var array<string> */
    public readonly array $schoolLevelsOffered;
    public readonly string $managementType;
    public readonly string $mediumOfInstruction;
    public readonly string $residentialStatus;
    public readonly string $boardAffiliationRef;
    public readonly ?string $boardAffiliationNumber;
    public readonly ?string $recognitionNumber;
    public readonly ?string $affiliationValidityStart;
    public readonly ?string $affiliationValidityEnd;
    public readonly ?string $udiseCode;
    public readonly ?string $stateBoardCode;
    public readonly ?int $principalEmployeeId;
    public readonly ?string $principalName;
    public readonly ?string $principalEmail;
    public readonly ?string $principalPhone;
    public readonly string $schoolEmail;
    public readonly string $schoolPhone;
    public readonly ?string $emergencyContact;
    public readonly ?int $primaryLogoId;
    public readonly ?int $documentLogoId;
    public readonly ?string $documentHeaderText;
    public readonly ?string $documentFooterText;
    public readonly ?string $primaryLogoPath;
    public readonly ?string $documentLogoPath;

    public function __construct(SchoolProfile $profile, ?string $primaryLogoPath = null, ?string $documentLogoPath = null)
    {
        $this->schoolId                 = $profile->school_id;
        $this->schoolName               = $profile->school_name;
        $this->shortName                = $profile->short_name;
        $this->schoolCode               = $profile->school_code;
        $this->addressLine1             = $profile->address_line1;
        $this->addressLine2             = $profile->address_line2;
        $this->city                     = $profile->city;
        $this->state                    = $profile->state;
        $this->district                 = $profile->district;
        $this->block                    = $profile->block;
        $this->pinCode                  = $profile->pin_code;
        $this->country                  = $profile->country;
        $this->schoolType               = $profile->school_type;
        $this->schoolLevelsOffered      = $profile->school_levels_offered ?? [];
        $this->managementType           = $profile->management_type;
        $this->mediumOfInstruction      = $profile->medium_of_instruction;
        $this->residentialStatus        = $profile->residential_status;
        $this->boardAffiliationRef      = $profile->board_affiliation_ref;
        $this->boardAffiliationNumber   = $profile->board_affiliation_number;
        $this->recognitionNumber        = $profile->recognition_number;
        $this->affiliationValidityStart = $profile->affiliation_validity_start;
        $this->affiliationValidityEnd   = $profile->affiliation_validity_end;
        $this->udiseCode                = $profile->udise_code;
        $this->stateBoardCode           = $profile->state_board_code;
        $this->principalEmployeeId      = $profile->principal_employee_id;
        $this->principalName            = $profile->principal_name;
        $this->principalEmail           = $profile->principal_email;
        $this->principalPhone           = $profile->principal_phone;
        $this->schoolEmail              = $profile->school_email;
        $this->schoolPhone              = $profile->school_phone;
        $this->emergencyContact         = $profile->emergency_contact;
        $this->primaryLogoId            = $profile->primary_logo_id;
        $this->documentLogoId           = $profile->document_logo_id;
        $this->documentHeaderText      = $profile->document_header_text;
        $this->documentFooterText       = $profile->document_footer_text;
        $this->primaryLogoPath          = $primaryLogoPath;
        $this->documentLogoPath         = $documentLogoPath;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'school_id'                  => $this->schoolId,
            'school_name'                => $this->schoolName,
            'short_name'                 => $this->shortName,
            'school_code'                => $this->schoolCode,
            'address_line1'              => $this->addressLine1,
            'address_line2'              => $this->addressLine2,
            'city'                       => $this->city,
            'state'                      => $this->state,
            'district'                   => $this->district,
            'block'                      => $this->block,
            'pin_code'                   => $this->pinCode,
            'country'                    => $this->country,
            'school_type'                => $this->schoolType,
            'school_levels_offered'      => $this->schoolLevelsOffered,
            'management_type'            => $this->managementType,
            'medium_of_instruction'      => $this->mediumOfInstruction,
            'residential_status'         => $this->residentialStatus,
            'board_affiliation_ref'      => $this->boardAffiliationRef,
            'board_affiliation_number'   => $this->boardAffiliationNumber,
            'recognition_number'         => $this->recognitionNumber,
            'affiliation_validity_start' => $this->affiliationValidityStart,
            'affiliation_validity_end'   => $this->affiliationValidityEnd,
            'udise_code'                 => $this->udiseCode,
            'state_board_code'           => $this->stateBoardCode,
            'principal_employee_id'      => $this->principalEmployeeId,
            'principal_name'             => $this->principalName,
            'principal_email'            => $this->principalEmail,
            'principal_phone'            => $this->principalPhone,
            'school_email'               => $this->schoolEmail,
            'school_phone'               => $this->schoolPhone,
            'emergency_contact'          => $this->emergencyContact,
            'primary_logo_id'            => $this->primaryLogoId,
            'document_logo_id'           => $this->documentLogoId,
            'document_header_text'       => $this->documentHeaderText,
            'document_footer_text'       => $this->documentFooterText,
            'primary_logo_path'          => $this->primaryLogoPath,
            'document_logo_path'         => $this->documentLogoPath,
        ];
    }
}
