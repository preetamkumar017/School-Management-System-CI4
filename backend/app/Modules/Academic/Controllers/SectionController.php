<?php

declare(strict_types=1);

namespace App\Modules\Academic\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Academic\DTOs\CreateSectionRequest;
use App\Modules\Academic\DTOs\UpdateSectionRequest;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/academic/Phase-5-Controller-Design.md
 * Base path /api/v1/academic/sections
 */
#[OA\Tag(name: 'Sections')]
class SectionController extends BaseController
{
    #[OA\Post(
        path: '/academic/sections',
        tags: ['Sections'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/SectionCreateRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/SectionResponse')),
            new OA\Response(response: 422, description: 'CLASS_NOT_FOUND or SECTION_NAME_ALREADY_TAKEN_IN_CLASS.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];

        $classId = (int) ($body['class_id'] ?? 0);
        [$sectionName, $capacity] = $this->validateFields($body);

        if ($classId <= 0) {
            throw new ValidationException(['class_id' => 'class_id is required.']);
        }

        $response = Services::sectionService()->createSection(
            new CreateSectionRequest($classId, $sectionName, $capacity),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Patch(
        path: '/academic/sections/{id}',
        tags: ['Sections'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/SectionUpdateRequest')),
        responses: [new OA\Response(response: 200, description: 'Updated.', content: new OA\JsonContent(ref: '#/components/schemas/SectionResponse'))],
    )]
    public function update(int $id)
    {
        [$sectionName, $capacity] = $this->validateFields($this->request->getJSON(true) ?? []);

        $response = Services::sectionService()->updateSection($id, new UpdateSectionRequest($sectionName, $capacity));

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Get(
        path: '/academic/sections/{id}',
        tags: ['Sections'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/SectionResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::sectionService()->getSection($id)->toArray());
    }

    #[OA\Get(
        path: '/academic/sections',
        tags: ['Sections'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'class_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/SectionResponse')),
            ),
            new OA\Response(response: 422, description: 'class_id is required.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function index()
    {
        $classId = (int) ($this->request->getGet('class_id') ?? 0);

        if ($classId <= 0) {
            throw new ValidationException(['class_id' => 'class_id query parameter is required.']);
        }

        $responses = Services::sectionService()->listSectionsByClass($classId);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: string, 1: int}
     */
    private function validateFields(array $body): array
    {
        $sectionName = (string) ($body['section_name'] ?? '');
        $fields      = [];

        if ($sectionName === '' || strlen($sectionName) > 10) {
            $fields['section_name'] = 'section_name is required and must be at most 10 characters.';
        }

        if (! isset($body['capacity']) || ! is_numeric($body['capacity']) || (int) $body['capacity'] <= 0) {
            $fields['capacity'] = 'capacity is required and must be a positive integer.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        return [$sectionName, (int) $body['capacity']];
    }
}
