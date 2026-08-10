<?php

declare(strict_types=1);

namespace App\Modules\Administration\Controllers;

use App\Core\BaseController;
use App\Modules\Administration\DTOs\SaveSchoolProfileRequest;
use Config\Services;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'SchoolProfile')]
class SchoolProfileController extends BaseController
{
    #[OA\Get(
        path: '/administration/school-profile',
        tags: ['SchoolProfile'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/SchoolProfileResponse')),
        ],
    )]
    public function show()
    {
        $profile = Services::schoolProfileService()->getProfile();
        if ($profile === null) {
            return $this->respondSuccess([]);
        }
        return $this->respondSuccess($profile->toArray());
    }

    #[OA\Post(
        path: '/administration/school-profile',
        tags: ['SchoolProfile'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/SaveSchoolProfileRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Saved.', content: new OA\JsonContent(ref: '#/components/schemas/SchoolProfileResponse')),
        ],
    )]
    public function save()
    {
        $body = $this->request->getJSON(true) ?? [];

        $request = new SaveSchoolProfileRequest(
            schoolName: (string)($body['school_name'] ?? ''),
            shortName: (string)($body['short_name'] ?? ''),
            schoolCode: isset($body['school_code']) ? (string)$body['school_code'] : null,
            addressLine1: (string)($body['address_line1'] ?? ''),
            addressLine2: (string)($body['address_line2'] ?? ''),
            city: (string)($body['city'] ?? ''),
            state: (string)($body['state'] ?? ''),
            district: isset($body['district']) ? (string)$body['district'] : null,
            block: isset($body['block']) ? (string)$body['block'] : null,
            pinCode: (string)($body['pin_code'] ?? ''),
            country: (string)($body['country'] ?? 'India'),
            schoolType: (string)($body['school_type'] ?? ''),
            schoolLevelsOffered: (array)($body['school_levels_offered'] ?? []),
            managementType: (string)($body['management_type'] ?? ''),
            mediumOfInstruction: (string)($body['medium_of_instruction'] ?? ''),
            residentialStatus: (string)($body['residential_status'] ?? ''),
            boardAffiliationRef: (string)($body['board_affiliation_ref'] ?? ''),
            boardAffiliationNumber: isset($body['board_affiliation_number']) ? (string)$body['board_affiliation_number'] : null,
            recognitionNumber: isset($body['recognition_number']) ? (string)$body['recognition_number'] : null,
            affiliationValidityStart: isset($body['affiliation_validity_start']) ? (string)$body['affiliation_validity_start'] : null,
            affiliationValidityEnd: isset($body['affiliation_validity_end']) ? (string)$body['affiliation_validity_end'] : null,
            udiseCode: isset($body['udise_code']) ? (string)$body['udise_code'] : null,
            stateBoardCode: isset($body['state_board_code']) ? (string)$body['state_board_code'] : null,
            principalEmployeeId: isset($body['principal_employee_id']) ? (int)$body['principal_employee_id'] : null,
            principalName: isset($body['principal_name']) ? (string)$body['principal_name'] : null,
            principalEmail: isset($body['principal_email']) ? (string)$body['principal_email'] : null,
            principalPhone: isset($body['principal_phone']) ? (string)$body['principal_phone'] : null,
            schoolEmail: (string)($body['school_email'] ?? ''),
            schoolPhone: (string)($body['school_phone'] ?? ''),
            emergencyContact: isset($body['emergency_contact']) ? (string)$body['emergency_contact'] : null,
            primaryLogoBase64: isset($body['primary_logo_base64']) ? (string)$body['primary_logo_base64'] : null,
            primaryLogoExtension: isset($body['primary_logo_extension']) ? (string)$body['primary_logo_extension'] : null,
            documentLogoBase64: isset($body['document_logo_base64']) ? (string)$body['document_logo_base64'] : null,
            documentLogoExtension: isset($body['document_logo_extension']) ? (string)$body['document_logo_extension'] : null,
            documentHeaderText: isset($body['document_header_text']) ? (string)$body['document_header_text'] : null,
            documentFooterText: isset($body['document_footer_text']) ? (string)$body['document_footer_text'] : null,
        );

        $response = Services::schoolProfileService()->saveProfile($request);

        return $this->respondSuccess($response->toArray());
    }

    public function getStates()
    {
        $states = Services::schoolProfileService()->getStates();
        return $this->respondSuccess(array_map(fn($s) => $s->toRawArray(), $states));
    }

    public function getDistricts(int $stateId)
    {
        $districts = Services::schoolProfileService()->getDistricts($stateId);
        return $this->respondSuccess(array_map(fn($d) => $d->toRawArray(), $districts));
    }

    public function getBlocks(int $districtId)
    {
        $blocks = Services::schoolProfileService()->getBlocks($districtId);
        return $this->respondSuccess(array_map(fn($b) => $b->toRawArray(), $blocks));
    }
}
