<?php

declare(strict_types=1);

namespace App\Modules\Academic\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Academic\DTOs\AcademicSessionStatusChangeRequest;
use App\Modules\Academic\DTOs\CreateAcademicSessionRequest;
use App\Modules\Academic\DTOs\UpdateAcademicSessionRequest;
use App\Modules\Academic\Entities\AcademicSession;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/academic/Phase-5-Controller-Design.md
 * Base path /api/v1/academic/sessions
 */
#[OA\Tag(name: 'Academic Sessions')]
class AcademicSessionController extends BaseController
{
    #[OA\Post(
        path: '/academic/sessions',
        tags: ['Academic Sessions'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/AcademicSessionRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/AcademicSessionResponse')),
            new OA\Response(response: 422, description: 'Validation failure or business rule violation.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];
        [$sessionName, $startDate, $endDate] = $this->validateFields($body);

        $response = Services::academicSessionService()->createSession(
            new CreateAcademicSessionRequest($sessionName, $startDate, $endDate),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Patch(
        path: '/academic/sessions/{id}',
        tags: ['Academic Sessions'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/AcademicSessionRequest')),
        responses: [new OA\Response(response: 200, description: 'Updated.', content: new OA\JsonContent(ref: '#/components/schemas/AcademicSessionResponse'))],
    )]
    public function update(int $id)
    {
        $body = $this->request->getJSON(true) ?? [];
        [$sessionName, $startDate, $endDate] = $this->validateFields($body);

        $response = Services::academicSessionService()->updateSession(
            $id,
            new UpdateAcademicSessionRequest($sessionName, $startDate, $endDate),
        );

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Post(
        path: '/academic/sessions/{id}/status',
        tags: ['Academic Sessions'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [new OA\Property(property: 'status', type: 'string', enum: ['PLANNED', 'ACTIVE', 'CLOSED', 'ARCHIVED'])],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Status changed.', content: new OA\JsonContent(ref: '#/components/schemas/AcademicSessionResponse')),
            new OA\Response(response: 422, description: 'ACADEMIC_SESSION_INVALID_STATUS_TRANSITION.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function changeStatus(int $id)
    {
        $body   = $this->request->getJSON(true) ?? [];
        $status = (string) ($body['status'] ?? '');

        $validStatuses = [
            AcademicSession::STATUS_PLANNED,
            AcademicSession::STATUS_ACTIVE,
            AcademicSession::STATUS_CLOSED,
            AcademicSession::STATUS_ARCHIVED,
        ];

        if (! in_array($status, $validStatuses, true)) {
            throw new ValidationException(['status' => 'status must be one of PLANNED, ACTIVE, CLOSED, ARCHIVED.']);
        }

        $response = Services::academicSessionService()->changeStatus(
            $id,
            new AcademicSessionStatusChangeRequest($status),
        );

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Get(
        path: '/academic/sessions/{id}',
        tags: ['Academic Sessions'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/AcademicSessionResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::academicSessionService()->getSession($id)->toArray());
    }

    #[OA\Get(
        path: '/academic/sessions',
        tags: ['Academic Sessions'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/AcademicSessionResponse')),
            ),
        ],
    )]
    public function index()
    {
        $responses = Services::academicSessionService()->listSessions();

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    #[OA\Get(
        path: '/academic/sessions/current',
        tags: ['Academic Sessions'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The current ACTIVE session, or null if none is active.',
                content: new OA\JsonContent(ref: '#/components/schemas/AcademicSessionResponse'),
            ),
        ],
    )]
    public function current()
    {
        $response = Services::academicSessionService()->getCurrentActiveSession();

        return $this->respondSuccess($response?->toArray());
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function validateFields(array $body): array
    {
        $sessionName = (string) ($body['session_name'] ?? '');
        $startDate   = (string) ($body['start_date'] ?? '');
        $endDate     = (string) ($body['end_date'] ?? '');

        $fields = [];

        if (preg_match('/^\d{4}-\d{2}$/', $sessionName) !== 1) {
            $fields['session_name'] = 'session_name is required and must match the format YYYY-YY.';
        }

        $startTimestamp = strtotime($startDate);
        $endTimestamp   = strtotime($endDate);

        if ($startTimestamp === false) {
            $fields['start_date'] = 'start_date is required and must be a valid date.';
        }

        if ($endTimestamp === false) {
            $fields['end_date'] = 'end_date is required and must be a valid date.';
        }

        if ($startTimestamp !== false && $endTimestamp !== false && $startTimestamp >= $endTimestamp) {
            $fields['end_date'] = 'end_date must be after start_date.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        return [$sessionName, $startDate, $endDate];
    }
}
