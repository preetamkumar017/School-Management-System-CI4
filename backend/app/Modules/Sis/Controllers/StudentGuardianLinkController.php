<?php

declare(strict_types=1);

namespace App\Modules\Sis\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Sis\DTOs\StudentGuardianLinkRequest;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/sis/Phase-4.7-Controller-Design.md
 * Base path /api/v1/sis/student-guardian-links
 */
#[OA\Tag(name: 'Student-Guardian Links')]
class StudentGuardianLinkController extends BaseController
{
    #[OA\Post(
        path: '/sis/student-guardian-links',
        tags: ['Student-Guardian Links'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StudentGuardianLinkRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/StudentGuardianLinkResponse')),
            new OA\Response(response: 422, description: 'STUDENT_NOT_FOUND, GUARDIAN_NOT_FOUND, or STUDENT_GUARDIAN_LINK_ALREADY_EXISTS.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body       = $this->request->getJSON(true) ?? [];
        $studentId  = (int) ($body['student_id'] ?? 0);
        $guardianId = (int) ($body['guardian_id'] ?? 0);
        $isPrimary  = (bool) ($body['is_primary_contact'] ?? false);

        $fields = [];

        if ($studentId <= 0) {
            $fields['student_id'] = 'student_id is required.';
        }

        if ($guardianId <= 0) {
            $fields['guardian_id'] = 'guardian_id is required.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $response = Services::studentGuardianLinkService()->linkGuardian(
            new StudentGuardianLinkRequest($studentId, $guardianId, $isPrimary),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Delete(
        path: '/sis/student-guardian-links/{studentId}/{guardianId}',
        tags: ['Student-Guardian Links'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'studentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'guardianId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Unlinked.'),
            new OA\Response(response: 422, description: 'STUDENT_GUARDIAN_LINK_NOT_FOUND.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function delete(int $studentId, int $guardianId)
    {
        Services::studentGuardianLinkService()->unlinkGuardian($studentId, $guardianId);

        return $this->respondSuccess();
    }

    #[OA\Get(
        path: '/sis/student-guardian-links/by-student/{studentId}',
        tags: ['Student-Guardian Links'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'studentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK — guardians linked to this student.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/StudentGuardianLinkResponse')),
            ),
        ],
    )]
    public function byStudent(int $studentId)
    {
        $responses = Services::studentGuardianLinkService()->listGuardiansForStudent($studentId);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }
}
