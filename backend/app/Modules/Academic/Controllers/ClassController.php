<?php

declare(strict_types=1);

namespace App\Modules\Academic\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Academic\DTOs\CreateClassRequest;
use App\Modules\Academic\DTOs\UpdateClassRequest;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/academic/Phase-5-Controller-Design.md
 * Base path /api/v1/academic/classes
 */
#[OA\Tag(name: 'Classes')]
class ClassController extends BaseController
{
    #[OA\Post(
        path: '/academic/classes',
        tags: ['Classes'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ClassRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/ClassResponse')),
            new OA\Response(response: 422, description: 'CLASS_NAME_ALREADY_TAKEN or CLASS_SEQUENCE_ORDER_ALREADY_TAKEN.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        [$className, $sequenceOrder] = $this->validateFields($this->request->getJSON(true) ?? []);

        $response = Services::classService()->createClass(new CreateClassRequest($className, $sequenceOrder));

        return $this->respondCreated($response->toArray());
    }

    #[OA\Patch(
        path: '/academic/classes/{id}',
        tags: ['Classes'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ClassRequest')),
        responses: [new OA\Response(response: 200, description: 'Updated.', content: new OA\JsonContent(ref: '#/components/schemas/ClassResponse'))],
    )]
    public function update(int $id)
    {
        [$className, $sequenceOrder] = $this->validateFields($this->request->getJSON(true) ?? []);

        $response = Services::classService()->updateClass($id, new UpdateClassRequest($className, $sequenceOrder));

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Get(
        path: '/academic/classes/{id}',
        tags: ['Classes'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/ClassResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::classService()->getClass($id)->toArray());
    }

    #[OA\Get(
        path: '/academic/classes',
        tags: ['Classes'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK — ordered by sequence_order.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/ClassResponse')),
            ),
        ],
    )]
    public function index()
    {
        $responses = Services::classService()->listClasses();

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: string, 1: int}
     */
    private function validateFields(array $body): array
    {
        $className = (string) ($body['class_name'] ?? '');
        $fields    = [];

        if ($className === '' || strlen($className) > 20) {
            $fields['class_name'] = 'class_name is required and must be at most 20 characters.';
        }

        if (! isset($body['sequence_order']) || ! is_numeric($body['sequence_order'])) {
            $fields['sequence_order'] = 'sequence_order is required and must be an integer.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        return [$className, (int) $body['sequence_order']];
    }
}
