<?php

declare(strict_types=1);

namespace App\Modules\Communication\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Communication\DTOs\CreateCircularRequest;
use App\Modules\Communication\Entities\Circular;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/communication/Phase-3-Service-Controller-Design.md
 * Base path /api/v1/communication/circulars
 */
#[OA\Tag(name: 'Circulars')]
class CircularController extends BaseController
{
    private const VALID_POST_TYPES = [
        Circular::POST_TYPE_HOMEWORK,
        Circular::POST_TYPE_CIRCULAR,
        Circular::POST_TYPE_ANNOUNCEMENT,
    ];

    #[OA\Post(
        path: '/communication/circulars',
        tags: ['Circulars'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CircularCreateRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/CircularResponse')),
            new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];

        $authorId       = (int) ($body['author_id'] ?? 0);
        $postType       = (string) ($body['post_type'] ?? '');
        $title          = (string) ($body['title'] ?? '');
        $post_body      = (string) ($body['body'] ?? '');
        $targetAudience = (string) ($body['target_audience'] ?? '');

        $fields = [];

        if ($authorId <= 0) {
            $fields['author_id'] = 'author_id is required.';
        }

        if (! in_array($postType, self::VALID_POST_TYPES, true)) {
            $fields['post_type'] = 'post_type must be one of Homework, Circular, Announcement.';
        }

        if ($title === '') {
            $fields['title'] = 'title is required.';
        }

        if ($post_body === '') {
            $fields['body'] = 'body is required.';
        }

        if ($targetAudience === '') {
            $fields['target_audience'] = 'target_audience is required.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $response = Services::circularService()->createCircular(
            new CreateCircularRequest($authorId, $postType, $title, $post_body, $targetAudience),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Post(
        path: '/communication/circulars/{id}/retract',
        tags: ['Circulars'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Retracted.', content: new OA\JsonContent(ref: '#/components/schemas/CircularResponse')),
            new OA\Response(response: 422, description: 'CIRCULAR_ALREADY_RETRACTED.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function retract(int $id)
    {
        return $this->respondSuccess(Services::circularService()->retract($id)->toArray());
    }

    #[OA\Get(
        path: '/communication/circulars/{id}',
        tags: ['Circulars'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/CircularResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::circularService()->getCircular($id)->toArray());
    }

    #[OA\Get(
        path: '/communication/circulars',
        tags: ['Circulars'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'target_audience', in: 'query', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/CircularResponse')),
            ),
        ],
    )]
    public function index()
    {
        $targetAudience = (string) ($this->request->getGet('target_audience') ?? '');

        if ($targetAudience === '') {
            throw new ValidationException(['target_audience' => 'target_audience query parameter is required.']);
        }

        $responses = Services::circularService()->listByTargetAudience($targetAudience);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }
}
