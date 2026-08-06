<?php

declare(strict_types=1);

namespace App\Modules\Academic\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Academic\DTOs\ClassSubjectMapRequest;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/academic/Phase-5-Controller-Design.md
 * Base path /api/v1/academic/class-subject-map
 */
#[OA\Tag(name: 'Class-Subject Mapping')]
class ClassSubjectMapController extends BaseController
{
    #[OA\Post(
        path: '/academic/class-subject-map',
        tags: ['Class-Subject Mapping'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ClassSubjectMapRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/ClassSubjectMapResponse')),
            new OA\Response(response: 422, description: 'CLASS_NOT_FOUND, SUBJECT_NOT_FOUND, or CLASS_SUBJECT_MAPPING_ALREADY_EXISTS.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body      = $this->request->getJSON(true) ?? [];
        $classId   = (int) ($body['class_id'] ?? 0);
        $subjectId = (int) ($body['subject_id'] ?? 0);

        $fields = [];

        if ($classId <= 0) {
            $fields['class_id'] = 'class_id is required.';
        }

        if ($subjectId <= 0) {
            $fields['subject_id'] = 'subject_id is required.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $response = Services::classSubjectMapService()->mapSubjectToClass(
            new ClassSubjectMapRequest($classId, $subjectId),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Delete(
        path: '/academic/class-subject-map/{classId}/{subjectId}',
        tags: ['Class-Subject Mapping'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'classId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'subjectId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Unmapped.'),
            new OA\Response(response: 422, description: 'CLASS_SUBJECT_MAPPING_NOT_FOUND.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function delete(int $classId, int $subjectId)
    {
        Services::classSubjectMapService()->unmapSubjectFromClass($classId, $subjectId);

        return $this->respondSuccess();
    }

    #[OA\Get(
        path: '/academic/class-subject-map/by-class/{classId}',
        tags: ['Class-Subject Mapping'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'classId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK — subjects mapped to this class.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/SubjectResponse')),
            ),
        ],
    )]
    public function byClass(int $classId)
    {
        $responses = Services::classSubjectMapService()->listSubjectsForClass($classId);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }
}
