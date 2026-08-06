<?php

declare(strict_types=1);

namespace App\Modules\Admission\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Admission\DTOs\ApplicationRejectRequest;
use App\Modules\Admission\DTOs\ApplicationShortlistRequest;
use App\Modules\Admission\DTOs\ApplicationVerifyRequest;
use App\Modules\Admission\DTOs\ApplicationWaitlistRequest;
use App\Modules\Admission\DTOs\CreateApplicationRequest;
use App\Modules\Admission\Entities\Application;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/admission/Phase-5-Controller-Design.md (core CRUD) and
 * docs/design/admission/Phase-6-Service-Design-Confirm-Enrollment.md
 * (confirm-enrollment, added in Stage 5 once SIS exists).
 * Base path /api/v1/admission/applications.
 */
#[OA\Tag(name: 'Applications')]
class ApplicationController extends BaseController
{
    /**
     * Verhoeff checksum tables — BR-ADM-006's "checksum-valid" Aadhaar
     * requirement. The Verhoeff algorithm (not Luhn) is the standard
     * Aadhaar checksum scheme.
     *
     * @var list<list<int>>
     */
    private const D = [
        [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
        [1, 2, 3, 4, 0, 6, 7, 8, 9, 5],
        [2, 3, 4, 0, 1, 7, 8, 9, 5, 6],
        [3, 4, 0, 1, 2, 8, 9, 5, 6, 7],
        [4, 0, 1, 2, 3, 9, 5, 6, 7, 8],
        [5, 9, 8, 7, 6, 0, 4, 3, 2, 1],
        [6, 5, 9, 8, 7, 1, 0, 4, 3, 2],
        [7, 6, 5, 9, 8, 2, 1, 0, 4, 3],
        [8, 7, 6, 5, 9, 3, 2, 1, 0, 4],
        [9, 8, 7, 6, 5, 4, 3, 2, 1, 0],
    ];

    /**
     * @var list<list<int>>
     */
    private const P = [
        [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
        [1, 5, 7, 6, 2, 8, 3, 0, 9, 4],
        [5, 8, 0, 3, 7, 9, 6, 1, 4, 2],
        [8, 9, 1, 6, 0, 4, 3, 5, 2, 7],
        [9, 4, 5, 3, 1, 2, 6, 8, 7, 0],
        [4, 2, 8, 6, 5, 7, 3, 9, 0, 1],
        [2, 7, 9, 3, 8, 0, 6, 4, 1, 5],
        [7, 0, 4, 6, 9, 1, 3, 2, 5, 8],
    ];

    #[OA\Post(
        path: '/admission/applications',
        tags: ['Applications'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ApplicationCreateRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/ApplicationResponse')),
            new OA\Response(response: 422, description: 'Validation failure or CLASS_NOT_FOUND.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];
        [$applicantName, $dob, $classAppliedId, $aadhaarNumber, $category] = $this->validateFields($body);

        $response = Services::applicationService()->createApplication(
            new CreateApplicationRequest($applicantName, $dob, $classAppliedId, $aadhaarNumber, $category),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Post(
        path: '/admission/applications/{id}/verify',
        tags: ['Applications'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'SUBMITTED -> VERIFIED.', content: new OA\JsonContent(ref: '#/components/schemas/ApplicationResponse')),
            new OA\Response(response: 422, description: 'APPLICATION_INVALID_STATUS_TRANSITION.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function verify(int $id)
    {
        $response = Services::applicationService()->verifyApplication($id, new ApplicationVerifyRequest());

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Post(
        path: '/admission/applications/{id}/shortlist',
        tags: ['Applications'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'VERIFIED -> SHORTLISTED.', content: new OA\JsonContent(ref: '#/components/schemas/ApplicationResponse')),
            new OA\Response(response: 422, description: 'APPLICATION_INVALID_STATUS_TRANSITION.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function shortlist(int $id)
    {
        $response = Services::applicationService()->shortlistApplication($id, new ApplicationShortlistRequest());

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Post(
        path: '/admission/applications/{id}/waitlist',
        tags: ['Applications'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'VERIFIED/SHORTLISTED -> WAITLISTED.', content: new OA\JsonContent(ref: '#/components/schemas/ApplicationResponse')),
            new OA\Response(response: 422, description: 'APPLICATION_INVALID_STATUS_TRANSITION.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function waitlist(int $id)
    {
        $response = Services::applicationService()->waitlistApplication($id, new ApplicationWaitlistRequest());

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Post(
        path: '/admission/applications/{id}/reject',
        tags: ['Applications'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Any pre-ADMITTED status -> REJECTED.', content: new OA\JsonContent(ref: '#/components/schemas/ApplicationResponse')),
            new OA\Response(response: 422, description: 'APPLICATION_INVALID_STATUS_TRANSITION.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function reject(int $id)
    {
        $response = Services::applicationService()->rejectApplication($id, new ApplicationRejectRequest());

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Post(
        path: '/admission/applications/{id}/confirm-enrollment',
        tags: ['Applications'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'FR-02 Confirm Enrollment — SHORTLISTED/WAITLISTED -> ADMITTED. Creates the linked Student stub in SIS and returns its student_id alongside the updated application.',
                content: new OA\JsonContent(ref: '#/components/schemas/ApplicationResponse'),
            ),
            new OA\Response(
                response: 422,
                description: 'APPLICATION_INVALID_STATUS_TRANSITION, NO_ACTIVE_ACADEMIC_SESSION, SEAT_ALLOCATION_NOT_FOUND, DUPLICATE_APPLICANT_IDENTITY, SEAT_CAPACITY_CEILING_REACHED, or RTE_QUOTA_CEILING_REACHED.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
        ],
    )]
    public function confirmEnrollment(int $id)
    {
        $result = Services::applicationService()->confirmEnrollment($id);

        return $this->respondSuccess($result->toArray());
    }

    #[OA\Get(
        path: '/admission/applications/{id}',
        tags: ['Applications'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/ApplicationResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::applicationService()->getApplication($id)->toArray());
    }

    #[OA\Get(
        path: '/admission/applications',
        tags: ['Applications'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['SUBMITTED', 'VERIFIED', 'SHORTLISTED', 'WAITLISTED', 'ADMITTED', 'REJECTED'])),
            new OA\Parameter(name: 'class_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK — paginated (Transaction-classified data).',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/ApplicationResponse')),
            ),
        ],
    )]
    public function index()
    {
        $status  = $this->request->getGet('status');
        $classId = $this->request->getGet('class_id');
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = max(1, (int) ($this->request->getGet('per_page') ?? 20));

        $result = Services::applicationService()->listApplications(
            $status !== null && $status !== '' ? (string) $status : null,
            $classId !== null && $classId !== '' ? (int) $classId : null,
            $page,
            $perPage,
        );

        return $this->respondPaginated(
            array_map(static fn ($response) => $response->toArray(), $result['items']),
            $page,
            $perPage,
            $result['total'],
        );
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: string, 1: string, 2: int, 3: ?string, 4: string}
     */
    private function validateFields(array $body): array
    {
        $applicantName  = (string) ($body['applicant_name'] ?? '');
        $dob            = (string) ($body['dob'] ?? '');
        $classAppliedId = (int) ($body['class_applied_id'] ?? 0);
        $aadhaarNumber  = isset($body['aadhaar_number']) && $body['aadhaar_number'] !== ''
            ? (string) $body['aadhaar_number']
            : null;
        $category = (string) ($body['category'] ?? Application::CATEGORY_GENERAL);

        $fields = [];

        if ($applicantName === '' || strlen($applicantName) > 100) {
            $fields['applicant_name'] = 'applicant_name is required and must be at most 100 characters.';
        }

        $dobTimestamp = strtotime($dob);

        if ($dobTimestamp === false || $dobTimestamp >= time()) {
            $fields['dob'] = 'dob is required and must be a valid date in the past.';
        }

        if ($classAppliedId <= 0) {
            $fields['class_applied_id'] = 'class_applied_id is required.';
        }

        if ($aadhaarNumber !== null && ! $this->isValidAadhaar($aadhaarNumber)) {
            $fields['aadhaar_number'] = 'aadhaar_number must be exactly 12 digits with a valid checksum.';
        }

        if (! in_array($category, [Application::CATEGORY_GENERAL, Application::CATEGORY_RTE], true)) {
            $fields['category'] = 'category must be one of GENERAL, RTE.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        return [$applicantName, $dob, $classAppliedId, $aadhaarNumber, $category];
    }

    private function isValidAadhaar(string $number): bool
    {
        if (preg_match('/^\d{12}$/', $number) !== 1) {
            return false;
        }

        $digits = array_reverse(array_map('intval', str_split($number)));
        $check  = 0;

        foreach ($digits as $i => $digit) {
            $check = self::D[$check][self::P[$i % 8][$digit]];
        }

        return $check === 0;
    }
}
