<?php

declare(strict_types=1);

namespace App\Modules\Academic\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Academic\DTOs\CreateSubjectRequest;
use App\Modules\Academic\DTOs\UpdateSubjectRequest;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/academic/Phase-5-Controller-Design.md
 * Base path /api/v1/academic/subjects
 */
#[OA\Tag(name: 'Subjects')]
class SubjectController extends BaseController
{
    #[OA\Post(
        path: '/academic/subjects',
        tags: ['Subjects'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/SubjectRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/SubjectResponse')),
            new OA\Response(response: 422, description: 'SUBJECT_CODE_ALREADY_TAKEN.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        [$subjectName, $subjectCode] = $this->validateFields($this->request->getJSON(true) ?? []);

        $response = Services::subjectService()->createSubject(new CreateSubjectRequest($subjectName, $subjectCode));

        return $this->respondCreated($response->toArray());
    }

    #[OA\Patch(
        path: '/academic/subjects/{id}',
        tags: ['Subjects'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/SubjectRequest')),
        responses: [new OA\Response(response: 200, description: 'Updated.', content: new OA\JsonContent(ref: '#/components/schemas/SubjectResponse'))],
    )]
    public function update(int $id)
    {
        [$subjectName, $subjectCode] = $this->validateFields($this->request->getJSON(true) ?? []);

        $response = Services::subjectService()->updateSubject($id, new UpdateSubjectRequest($subjectName, $subjectCode));

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Get(
        path: '/academic/subjects/{id}',
        tags: ['Subjects'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/SubjectResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::subjectService()->getSubject($id)->toArray());
    }

    #[OA\Get(
        path: '/academic/subjects',
        tags: ['Subjects'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/SubjectResponse')),
            ),
        ],
    )]
    public function index()
    {
        $responses = Services::subjectService()->listSubjects();

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: string, 1: string}
     */
    private function validateFields(array $body): array
    {
        $subjectName = (string) ($body['subject_name'] ?? '');
        $subjectCode = (string) ($body['subject_code'] ?? '');
        $fields      = [];

        if ($subjectName === '') {
            $fields['subject_name'] = 'subject_name is required.';
        }

        if ($subjectCode === '' || strlen($subjectCode) > 10) {
            $fields['subject_code'] = 'subject_code is required and must be at most 10 characters.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        return [$subjectName, $subjectCode];
    }
}
