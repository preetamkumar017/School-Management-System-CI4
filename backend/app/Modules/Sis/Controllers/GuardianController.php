<?php

declare(strict_types=1);

namespace App\Modules\Sis\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Sis\DTOs\CreateGuardianRequest;
use App\Modules\Sis\DTOs\UpdateGuardianRequest;
use App\Modules\Sis\Entities\Guardian;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/sis/Phase-4.7-Controller-Design.md
 * Base path /api/v1/sis/guardians
 */
#[OA\Tag(name: 'Guardians')]
class GuardianController extends BaseController
{
    #[OA\Post(
        path: '/sis/guardians',
        tags: ['Guardians'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/GuardianRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/GuardianResponse')),
            new OA\Response(response: 422, description: 'Validation failure.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        [$fullName, $relationship, $mobileNumber, $email] = $this->validateFields($this->request->getJSON(true) ?? []);

        $response = Services::guardianService()->createGuardian(
            new CreateGuardianRequest($fullName, $relationship, $mobileNumber, $email),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Patch(
        path: '/sis/guardians/{id}',
        tags: ['Guardians'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/GuardianRequest')),
        responses: [new OA\Response(response: 200, description: 'Updated.', content: new OA\JsonContent(ref: '#/components/schemas/GuardianResponse'))],
    )]
    public function update(int $id)
    {
        [$fullName, $relationship, $mobileNumber, $email] = $this->validateFields($this->request->getJSON(true) ?? []);

        $response = Services::guardianService()->updateGuardian(
            $id,
            new UpdateGuardianRequest($fullName, $relationship, $mobileNumber, $email),
        );

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Get(
        path: '/sis/guardians/{id}',
        tags: ['Guardians'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/GuardianResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::guardianService()->getGuardian($id)->toArray());
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: string, 1: string, 2: string, 3: ?string}
     */
    private function validateFields(array $body): array
    {
        $fullName     = (string) ($body['full_name'] ?? '');
        $relationship = (string) ($body['relationship'] ?? '');
        $mobileNumber = (string) ($body['mobile_number'] ?? '');
        $email        = isset($body['email']) && $body['email'] !== '' ? (string) $body['email'] : null;

        $fields = [];

        if ($fullName === '' || strlen($fullName) > 100) {
            $fields['full_name'] = 'full_name is required and must be at most 100 characters.';
        }

        $validRelationships = [
            Guardian::RELATIONSHIP_FATHER,
            Guardian::RELATIONSHIP_MOTHER,
            Guardian::RELATIONSHIP_GUARDIAN,
            Guardian::RELATIONSHIP_OTHER,
        ];

        if (! in_array($relationship, $validRelationships, true)) {
            $fields['relationship'] = 'relationship must be one of FATHER, MOTHER, GUARDIAN, OTHER.';
        }

        if (preg_match('/^\d{10}$/', $mobileNumber) !== 1) {
            $fields['mobile_number'] = 'mobile_number is required and must be exactly 10 digits.';
        }

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $fields['email'] = 'email must be a valid email address.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        return [$fullName, $relationship, $mobileNumber, $email];
    }
}
