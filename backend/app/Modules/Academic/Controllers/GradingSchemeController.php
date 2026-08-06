<?php

declare(strict_types=1);

namespace App\Modules\Academic\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Academic\DTOs\CreateGradingSchemeRequest;
use App\Modules\Academic\DTOs\UpdateGradingSchemeRequest;
use App\Modules\Academic\Entities\GradingScheme;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/academic/Phase-5-Controller-Design.md
 * Base path /api/v1/academic/grading-schemes
 */
#[OA\Tag(name: 'Grading Schemes')]
class GradingSchemeController extends BaseController
{
    private const BOARD_TYPES = [
        GradingScheme::BOARD_CBSE,
        GradingScheme::BOARD_ICSE,
        GradingScheme::BOARD_STATE_BOARD,
    ];

    #[OA\Post(
        path: '/academic/grading-schemes',
        tags: ['Grading Schemes'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/GradingSchemeCreateRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/GradingSchemeResponse')),
            new OA\Response(response: 422, description: 'GRADING_SCHEME_NAME_ALREADY_TAKEN or invalid grade_band_json.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];

        $schemeName = (string) ($body['scheme_name'] ?? '');
        [$boardType, $gradeBandJson] = $this->validateFields($body);

        if ($schemeName === '' || strlen($schemeName) > 50) {
            throw new ValidationException(['scheme_name' => 'scheme_name is required and must be at most 50 characters.']);
        }

        $response = Services::gradingSchemeService()->createGradingScheme(
            new CreateGradingSchemeRequest($schemeName, $boardType, $gradeBandJson),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Patch(
        path: '/academic/grading-schemes/{id}',
        tags: ['Grading Schemes'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/GradingSchemeUpdateRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Updated.', content: new OA\JsonContent(ref: '#/components/schemas/GradingSchemeResponse')),
            new OA\Response(
                response: 422,
                description: 'GRADING_SCHEME_LOCKED_BY_CLOSED_EXAM — create a new scheme via POST / instead.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
        ],
    )]
    public function update(int $id)
    {
        [$boardType, $gradeBandJson] = $this->validateFields($this->request->getJSON(true) ?? []);

        $response = Services::gradingSchemeService()->updateGradingScheme(
            $id,
            new UpdateGradingSchemeRequest($boardType, $gradeBandJson),
        );

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Get(
        path: '/academic/grading-schemes/{id}',
        tags: ['Grading Schemes'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/GradingSchemeResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::gradingSchemeService()->getGradingScheme($id)->toArray());
    }

    #[OA\Get(
        path: '/academic/grading-schemes',
        tags: ['Grading Schemes'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/GradingSchemeResponse')),
            ),
        ],
    )]
    public function index()
    {
        $responses = Services::gradingSchemeService()->listGradingSchemes();

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function validateFields(array $body): array
    {
        $boardType = (string) ($body['board_type'] ?? '');
        $fields    = [];

        if (! in_array($boardType, self::BOARD_TYPES, true)) {
            $fields['board_type'] = 'board_type is required and must be one of CBSE, ICSE, STATE_BOARD.';
        }

        $gradeBandJson = $body['grade_band_json'] ?? null;

        if (! is_array($gradeBandJson) || $gradeBandJson === []) {
            $fields['grade_band_json'] = 'grade_band_json is required and must be a non-empty object.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        /** @var array<string, string> $gradeBandJson */
        return [$boardType, $gradeBandJson];
    }
}
