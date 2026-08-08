<?php

declare(strict_types=1);

namespace App\Modules\Sis\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Sis\DTOs\StudentSectionTransferRequest;
use App\Modules\Sis\DTOs\StudentStatusChangeRequest;
use App\Modules\Sis\DTOs\UpdateStudentRequest;
use App\Modules\Sis\DTOs\UploadStudentPhotoRequest;
use App\Modules\Sis\Entities\Student;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/sis/Phase-4.7-Controller-Design.md
 * Base path /api/v1/sis/students. Deliberately no POST / create route
 * (ADR-004 §3) — StudentService::createStudentStub is called only from
 * Admission's Confirm Enrollment orchestration.
 */
#[OA\Tag(name: 'Students')]
class StudentController extends BaseController
{
    #[OA\Patch(
        path: '/sis/students/{id}',
        tags: ['Students'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StudentUpdateRequest')),
        responses: [new OA\Response(response: 200, description: 'Updated.', content: new OA\JsonContent(ref: '#/components/schemas/StudentResponse'))],
    )]
    public function update(int $id)
    {
        [$fullName, $dob, $category, $aadhaarNumber, $medicalInfo] = $this->validateUpdateFields($this->request->getJSON(true) ?? []);

        $response = Services::studentService()->updateStudent(
            $id,
            new UpdateStudentRequest($fullName, $dob, $category, $aadhaarNumber, $medicalInfo),
        );

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Post(
        path: '/sis/students/{id}/section-transfer',
        tags: ['Students'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['new_section_id'], properties: [new OA\Property(property: 'new_section_id', type: 'integer')]),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Serves both a stub\'s first assignment and any later transfer.', content: new OA\JsonContent(ref: '#/components/schemas/StudentResponse')),
            new OA\Response(response: 422, description: 'SECTION_NOT_FOUND or SECTION_CAPACITY_EXCEEDED.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function sectionTransfer(int $id)
    {
        $body         = $this->request->getJSON(true) ?? [];
        $newSectionId = (int) ($body['new_section_id'] ?? 0);

        if ($newSectionId <= 0) {
            throw new ValidationException(['new_section_id' => 'new_section_id is required.']);
        }

        $response = Services::studentService()->transferSection($id, new StudentSectionTransferRequest($newSectionId));

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Post(
        path: '/sis/students/{id}/status',
        tags: ['Students'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [new OA\Property(property: 'status', type: 'string', enum: ['DRAFT', 'ACTIVE', 'PROMOTED', 'EXITED', 'ARCHIVED'])],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Status changed.', content: new OA\JsonContent(ref: '#/components/schemas/StudentResponse')),
            new OA\Response(response: 422, description: 'STUDENT_INVALID_STATUS_TRANSITION, STUDENT_PROFILE_INCOMPLETE, or STUDENT_NO_GUARDIAN_LINKED.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function changeStatus(int $id)
    {
        $body   = $this->request->getJSON(true) ?? [];
        $status = (string) ($body['status'] ?? '');

        $validStatuses = [
            Student::STATUS_DRAFT,
            Student::STATUS_ACTIVE,
            Student::STATUS_PROMOTED,
            Student::STATUS_EXITED,
            Student::STATUS_ARCHIVED,
        ];

        if (! in_array($status, $validStatuses, true)) {
            throw new ValidationException(['status' => 'status must be one of DRAFT, ACTIVE, PROMOTED, EXITED, ARCHIVED.']);
        }

        $response = Services::studentService()->changeStatus($id, new StudentStatusChangeRequest($status));

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Get(
        path: '/sis/students/{id}',
        tags: ['Students'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/StudentResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::studentService()->getStudent($id)->toArray());
    }

    #[OA\Get(
        path: '/sis/students',
        tags: ['Students'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'section_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK — section roster.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/StudentResponse')),
            ),
            new OA\Response(response: 422, description: 'section_id is required.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function index()
    {
        $sectionId = (int) ($this->request->getGet('section_id') ?? 0);

        if ($sectionId <= 0) {
            throw new ValidationException(['section_id' => 'section_id query parameter is required.']);
        }

        $responses = Services::studentService()->listStudentsBySection($sectionId);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    /**
     * docs/ADR/ADR-023-sis-id-card-generation.md §2. JSON body carries
     * base64 image data (image_base64/extension) — this codebase's API
     * surface is JSON-only everywhere else, no multipart endpoint exists.
     */
    #[OA\Post(
        path: '/sis/students/{id}/photo',
        tags: ['Students'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['image_base64', 'extension'],
                properties: [
                    new OA\Property(property: 'image_base64', type: 'string', description: 'Base64-encoded JPEG/PNG image data.'),
                    new OA\Property(property: 'extension', type: 'string', enum: ['jpg', 'jpeg', 'png']),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Photo stored, Student.photo_document_id updated.', content: new OA\JsonContent(ref: '#/components/schemas/StudentResponse')),
            new OA\Response(response: 422, description: 'STUDENT_PHOTO_INVALID_EXTENSION or STUDENT_PHOTO_INVALID_DATA.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function uploadPhoto(int $id)
    {
        $body        = $this->request->getJSON(true) ?? [];
        $imageBase64 = (string) ($body['image_base64'] ?? '');
        $extension   = (string) ($body['extension'] ?? '');

        if ($imageBase64 === '' || $extension === '') {
            throw new ValidationException(['image_base64' => 'image_base64 and extension are both required.']);
        }

        $response = Services::studentService()->uploadPhoto($id, new UploadStudentPhotoRequest($imageBase64, $extension));

        return $this->respondSuccess($response->toArray());
    }

    /**
     * docs/ADR/ADR-023-sis-id-card-generation.md §3/§4 — generic
     * placeholder-branding ID card PDF; embeds the real uploaded photo
     * when Student.photo_document_id is set, else a placeholder box.
     */
    #[OA\Get(
        path: '/sis/students/{id}/id-card',
        tags: ['Students'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 201, description: 'ID card PDF generated and stored as a Document.', content: new OA\JsonContent(ref: '#/components/schemas/DocumentResponse'))],
    )]
    public function generateIdCard(int $id)
    {
        return $this->respondCreated(Services::studentService()->generateIdCardPdf($id)->toArray());
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: string, 1: string, 2: string, 3: ?string, 4: ?string}
     */
    private function validateUpdateFields(array $body): array
    {
        $fullName = (string) ($body['full_name'] ?? '');
        $dob      = (string) ($body['dob'] ?? '');
        $category = (string) ($body['category'] ?? '');
        $aadhaarNumber = isset($body['aadhaar_number']) && $body['aadhaar_number'] !== ''
            ? (string) $body['aadhaar_number']
            : null;
        $medicalInfo = isset($body['medical_info']) && $body['medical_info'] !== ''
            ? (string) $body['medical_info']
            : null;

        $fields = [];

        if ($fullName === '' || strlen($fullName) > 100) {
            $fields['full_name'] = 'full_name is required and must be at most 100 characters.';
        }

        $dobTimestamp = strtotime($dob);

        if ($dobTimestamp === false || $dobTimestamp >= time()) {
            $fields['dob'] = 'dob is required and must be a valid date in the past.';
        }

        if (! in_array($category, [Student::CATEGORY_GENERAL, Student::CATEGORY_RTE], true)) {
            $fields['category'] = 'category must be one of GENERAL, RTE.';
        }

        if ($aadhaarNumber !== null && preg_match('/^\d{12}$/', $aadhaarNumber) !== 1) {
            $fields['aadhaar_number'] = 'aadhaar_number must be exactly 12 digits.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        return [$fullName, $dob, $category, $aadhaarNumber, $medicalInfo];
    }
}
