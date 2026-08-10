<?php

declare(strict_types=1);

namespace App\Modules\Administration\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Core\Exceptions\ValidationException;
use App\Modules\Administration\DTOs\SaveSchoolProfileRequest;
use App\Modules\Administration\DTOs\SchoolProfileResponse;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Entities\Document;
use App\Modules\Administration\Models\SchoolProfileModel;
use App\Modules\Administration\Models\StateModel;
use App\Modules\Administration\Models\DistrictModel;
use App\Modules\Administration\Models\BlockModel;
use App\Modules\HrPayroll\Models\EmployeeModel;
use App\Core\Http\RequestContext;
use Config\Services;

class SchoolProfileService
{
    private const ALLOWED_LOGO_EXTENSIONS = ['jpg', 'jpeg', 'png'];
    private const MAX_LOGO_SIZE           = 2097152; // 2MB

    public function __construct(
        private readonly SchoolProfileModel $schoolProfileModel,
        private readonly DocumentService $documentService,
        private readonly AuditService $auditService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
        private readonly EmployeeModel $employeeModel
    ) {
    }

    public function getProfile(): ?SchoolProfileResponse
    {
        $profile = $this->schoolProfileModel->where('is_deleted', false)->first();
        if ($profile === null) {
            return null;
        }

        $primaryLogoPath = null;
        if ($profile->primary_logo_id !== null) {
            try {
                $primaryLogoPath = $this->documentService->getDocument((int)$profile->primary_logo_id)->filePath;
            } catch (\Exception $e) {
                // Ignore missing document row
            }
        }

        $documentLogoPath = null;
        if ($profile->document_logo_id !== null) {
            try {
                $documentLogoPath = $this->documentService->getDocument((int)$profile->document_logo_id)->filePath;
            } catch (\Exception $e) {
                // Ignore missing document row
            }
        }

        return new SchoolProfileResponse($profile, $primaryLogoPath, $documentLogoPath);
    }

    public function saveProfile(SaveSchoolProfileRequest $request): SchoolProfileResponse
    {
        $this->moduleAuthorizer->assertManage('administration.manage');

        $errors = [];

        // 1. Basic Required Field Validations
        if (trim($request->schoolName) === '') {
            $errors['school_name'] = 'School name is required.';
        }
        if (trim($request->shortName) === '') {
            $errors['short_name'] = 'Short name / abbreviation is required.';
        }
        if (trim($request->addressLine1) === '') {
            $errors['address_line1'] = 'Address Line 1 is required.';
        }
        if (trim($request->addressLine2) === '') {
            $errors['address_line2'] = 'Address Line 2 is required.';
        }
        if (trim($request->city) === '') {
            $errors['city'] = 'City is required.';
        }
        if (trim($request->state) === '') {
            $errors['state'] = 'State is required.';
        } else {
            $stateObj = (new StateModel())->where('name', $request->state)->where('is_deleted', false)->first();
            if ($stateObj === null) {
                $errors['state'] = 'Selected state is not valid.';
            } else {
                if ($request->district !== null && trim($request->district) !== '') {
                    $districtObj = (new DistrictModel())->where('state_id', $stateObj->state_id)->where('name', $request->district)->where('is_deleted', false)->first();
                    if ($districtObj === null) {
                        $errors['district'] = 'Selected district is not valid for the selected state.';
                    } else {
                        if ($request->block !== null && trim($request->block) !== '') {
                            $blockObj = (new BlockModel())->where('district_id', $districtObj->district_id)->where('name', $request->block)->where('is_deleted', false)->first();
                            if ($blockObj === null) {
                                $errors['block'] = 'Selected block/tehsil is not valid for the selected district.';
                            }
                        }
                    }
                }
            }
        }
        if (trim($request->pinCode) === '') {
            $errors['pin_code'] = 'PIN Code is required.';
        } elseif (!preg_match('/^\d{6}$/', $request->pinCode)) {
            $errors['pin_code'] = 'PIN Code must be exactly 6 digits.';
        }
        if (trim($request->country) === '') {
            $errors['country'] = 'Country is required.';
        }
        if (trim($request->schoolType) === '') {
            $errors['school_type'] = 'School Type is required.';
        }
        if (empty($request->schoolLevelsOffered)) {
            $errors['school_levels_offered'] = 'At least one school level must be offered.';
        }
        if (trim($request->managementType) === '') {
            $errors['management_type'] = 'Management Type is required.';
        }
        if (trim($request->mediumOfInstruction) === '') {
            $errors['medium_of_instruction'] = 'Medium of instruction is required.';
        }
        if (trim($request->residentialStatus) === '') {
            $errors['residential_status'] = 'Residential status is required.';
        }
        if (trim($request->boardAffiliationRef) === '') {
            $errors['board_affiliation_ref'] = 'Board affiliation reference is required.';
        }

        // Email & Phone
        if (trim($request->schoolEmail) === '') {
            $errors['school_email'] = 'School email is required.';
        } elseif (!filter_var($request->schoolEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['school_email'] = 'School email must be a valid email address.';
        }
        if (trim($request->schoolPhone) === '') {
            $errors['school_phone'] = 'School phone is required.';
        }

        // UDISE+ Code
        if ($request->udiseCode !== null && trim($request->udiseCode) !== '') {
            if (!preg_match('/^\d{11}$/', $request->udiseCode)) {
                $errors['udise_code'] = 'UDISE+ School Code must be exactly 11 digits.';
            }
        }

        // Principal HR Link
        if ($request->principalEmployeeId !== null && $request->principalEmployeeId > 0) {
            $employee = $this->employeeModel->where('employee_id', $request->principalEmployeeId)->where('is_deleted', false)->first();
            if ($employee === null) {
                $errors['principal_employee_id'] = 'Selected Principal Employee does not exist.';
            }
        }

        // Check if profile exists already
        $existing = $this->schoolProfileModel->where('is_deleted', false)->first();

        // Logo Upload Requirements on Create
        if ($existing === null) {
            if ($request->primaryLogoBase64 === null || trim($request->primaryLogoBase64) === '') {
                $errors['primary_logo'] = 'Primary logo upload is required.';
            }
            if ($request->documentLogoBase64 === null || trim($request->documentLogoBase64) === '') {
                $errors['document_logo'] = 'Document logo upload is required.';
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        // 2. Decode & Store Logos
        $primaryLogoId = $existing ? $existing->primary_logo_id : null;
        if ($request->primaryLogoBase64 !== null && trim($request->primaryLogoBase64) !== '') {
            $ext = strtolower((string)$request->primaryLogoExtension);
            if (!in_array($ext, self::ALLOWED_LOGO_EXTENSIONS, true)) {
                throw new BusinessRuleException('SCHOOL_LOGO_INVALID_EXTENSION', 'Primary logo extension must be one of: ' . implode(', ', self::ALLOWED_LOGO_EXTENSIONS));
            }
            $bytes = base64_decode($request->primaryLogoBase64, true);
            if ($bytes === false || $bytes === '') {
                throw new BusinessRuleException('SCHOOL_LOGO_INVALID_DATA', 'Primary logo base64 data is invalid.');
            }
            if (strlen($bytes) > self::MAX_LOGO_SIZE) {
                throw new BusinessRuleException('SCHOOL_LOGO_TOO_LARGE', 'Primary logo file size cannot exceed 2MB.');
            }

            $doc = $this->documentService->store(
                'SchoolProfile',
                $existing ? (int)$existing->school_id : 1,
                'School Primary Logo',
                $bytes,
                (int)RequestContext::userId(),
                $ext
            );
            $primaryLogoId = $doc->documentId;
        }

        $documentLogoId = $existing ? $existing->document_logo_id : null;
        if ($request->documentLogoBase64 !== null && trim($request->documentLogoBase64) !== '') {
            $ext = strtolower((string)$request->documentLogoExtension);
            if (!in_array($ext, self::ALLOWED_LOGO_EXTENSIONS, true)) {
                throw new BusinessRuleException('SCHOOL_LOGO_INVALID_EXTENSION', 'Document logo extension must be one of: ' . implode(', ', self::ALLOWED_LOGO_EXTENSIONS));
            }
            $bytes = base64_decode($request->documentLogoBase64, true);
            if ($bytes === false || $bytes === '') {
                throw new BusinessRuleException('SCHOOL_LOGO_INVALID_DATA', 'Document logo base64 data is invalid.');
            }
            if (strlen($bytes) > self::MAX_LOGO_SIZE) {
                throw new BusinessRuleException('SCHOOL_LOGO_TOO_LARGE', 'Document logo file size cannot exceed 2MB.');
            }

            $doc = $this->documentService->store(
                'SchoolProfile',
                $existing ? (int)$existing->school_id : 1,
                'School Document Logo',
                $bytes,
                (int)RequestContext::userId(),
                $ext
            );
            $documentLogoId = $doc->documentId;
        }

        $pName = $request->principalName;
        $pEmail = $request->principalEmail;
        $pPhone = $request->principalPhone;

        if ($request->principalEmployeeId !== null && $request->principalEmployeeId > 0) {
            $emp = $this->employeeModel->find($request->principalEmployeeId);
            if ($emp !== null) {
                $pName = $emp->first_name . ' ' . $emp->last_name;
                $pEmail = $emp->email;
                $pPhone = $emp->emergency_contact_phone ?? $emp->emergency_contact_name; // fallback or general phone
            }
        }

        $data = [
            'school_name'                => $request->schoolName,
            'short_name'                 => $request->shortName,
            'school_code'                => $request->schoolCode,
            'address_line1'              => $request->addressLine1,
            'address_line2'              => $request->addressLine2,
            'city'                       => $request->city,
            'state'                      => $request->state,
            'district'                   => $request->district,
            'block'                      => $request->block,
            'pin_code'                   => $request->pinCode,
            'country'                    => $request->country,
            'school_type'                => $request->schoolType,
            'school_levels_offered'      => $request->schoolLevelsOffered,
            'management_type'            => $request->managementType,
            'medium_of_instruction'      => $request->mediumOfInstruction,
            'residential_status'         => $request->residentialStatus,
            'board_affiliation_ref'      => $request->boardAffiliationRef,
            'board_affiliation_number'   => $request->boardAffiliationNumber,
            'recognition_number'         => $request->recognitionNumber,
            'affiliation_validity_start' => $request->affiliationValidityStart ?: null,
            'affiliation_validity_end'   => $request->affiliationValidityEnd ?: null,
            'udise_code'                 => $request->udiseCode,
            'state_board_code'           => $request->stateBoardCode,
            'principal_employee_id'      => $request->principalEmployeeId,
            'principal_name'             => $pName,
            'principal_email'            => $pEmail,
            'principal_phone'            => $pPhone,
            'school_email'               => $request->schoolEmail,
            'school_phone'               => $request->schoolPhone,
            'emergency_contact'          => $request->emergencyContact,
            'primary_logo_id'            => $primaryLogoId,
            'document_logo_id'           => $documentLogoId,
            'document_header_text'       => $request->documentHeaderText,
            'document_footer_text'       => $request->documentFooterText,
        ];

        if ($existing === null) {
            $schoolId = $this->schoolProfileModel->insert($data, true);
            $profile = $this->schoolProfileModel->find($schoolId);

            // update document owner refs for logo to correct schoolId
            if ($primaryLogoId !== null) {
                $this->documentService->store('SchoolProfile', (int)$schoolId, 'School Primary Logo', $bytes, (int)RequestContext::userId(), $request->primaryLogoExtension);
            }

            $this->auditService->record('SchoolProfile', (int)$schoolId, AuditLog::ACTION_CREATE, null, $profile->toRawArray());
        } else {
            $schoolId = $existing->school_id;
            $before = $existing->toRawArray();
            $this->schoolProfileModel->update($schoolId, $data);
            $profile = $this->schoolProfileModel->find($schoolId);
            $this->auditService->record('SchoolProfile', (int)$schoolId, AuditLog::ACTION_UPDATE, $before, $profile->toRawArray());
        }

        $primaryLogoPath = $primaryLogoId ? $this->documentService->getDocument((int)$primaryLogoId)->filePath : null;
        $documentLogoPath = $documentLogoId ? $this->documentService->getDocument((int)$documentLogoId)->filePath : null;

        return new SchoolProfileResponse($profile, $primaryLogoPath, $documentLogoPath);
    }

    public function getStates(): array
    {
        return (new StateModel())->where('is_deleted', false)->findAll();
    }

    public function getDistricts(int $stateId): array
    {
        return (new DistrictModel())->where('state_id', $stateId)->where('is_deleted', false)->findAll();
    }

    public function getBlocks(int $districtId): array
    {
        return (new BlockModel())->where('district_id', $districtId)->where('is_deleted', false)->findAll();
    }
}
