<?php

declare(strict_types=1);

namespace App\Modules\Administration\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Administration\DTOs\UpdateConfigurationRequest;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/administration/Phase-7-Configuration-Design.md
 * Base path /api/v1/administration/configurations
 * No POST / — settings are seeded at migration time (ADR-011 §2).
 */
#[OA\Tag(name: 'Configurations')]
class ConfigurationController extends BaseController
{
    #[OA\Patch(
        path: '/administration/configurations/{key}',
        tags: ['Configurations'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'key', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/ConfigurationUpdateRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Updated.', content: new OA\JsonContent(ref: '#/components/schemas/ConfigurationResponse')),
            new OA\Response(response: 422, description: 'CONFIGURATION_NOT_EDITABLE.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function update(string $key)
    {
        $body         = $this->request->getJSON(true) ?? [];
        $settingValue = (string) ($body['setting_value'] ?? '');

        if ($settingValue === '') {
            throw new ValidationException(['setting_value' => 'setting_value is required.']);
        }

        $response = Services::configurationService()->updateByKey($key, new UpdateConfigurationRequest($settingValue));

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Get(
        path: '/administration/configurations/{key}',
        tags: ['Configurations'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'key', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/ConfigurationResponse'))],
    )]
    public function show(string $key)
    {
        return $this->respondSuccess(Services::configurationService()->getConfiguration($key)->toArray());
    }

    #[OA\Get(
        path: '/administration/configurations',
        tags: ['Configurations'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'module', in: 'query', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/ConfigurationResponse')),
            ),
        ],
    )]
    public function index()
    {
        $module = (string) ($this->request->getGet('module') ?? '');

        if ($module === '') {
            throw new ValidationException(['module' => 'module query parameter is required.']);
        }

        $responses = Services::configurationService()->listByModule($module);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }
}
