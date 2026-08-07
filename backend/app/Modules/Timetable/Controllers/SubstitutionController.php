<?php

declare(strict_types=1);

namespace App\Modules\Timetable\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Timetable\DTOs\CreateSubstitutionRequest;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/timetable/Phase-4-Substitution-Design.md (FR-16 / BR-TT-004)
 * Base path /api/v1/timetable/substitutions
 */
#[OA\Tag(name: 'Timetable Substitutions')]
class SubstitutionController extends BaseController
{
    #[OA\Post(
        path: '/timetable/substitutions',
        tags: ['Timetable Substitutions'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/SubstitutionRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created — ASSIGNED if an eligible substitute exists, UNSUPERVISED otherwise (ADR-013 §1).', content: new OA\JsonContent(ref: '#/components/schemas/SubstitutionResponse')),
            new OA\Response(response: 422, description: 'TIMETABLE_ENTRY_NOT_PUBLISHED, SUBSTITUTION_ALREADY_EXISTS, TEACHER_NOT_ABSENT, SUBSTITUTE_NOT_ELIGIBLE, or SUBSTITUTE_NOT_AVAILABLE.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body               = $this->request->getJSON(true) ?? [];
        $timetableEntryId   = (int) ($body['timetable_entry_id'] ?? 0);
        $substitutionDate   = (string) ($body['substitution_date'] ?? '');
        $substituteEmployee = isset($body['substitute_employee_id']) && $body['substitute_employee_id'] !== ''
            ? (int) $body['substitute_employee_id']
            : null;

        $fields = [];

        if ($timetableEntryId <= 0) {
            $fields['timetable_entry_id'] = 'timetable_entry_id is required.';
        }

        if (strtotime($substitutionDate) === false) {
            $fields['substitution_date'] = 'substitution_date is required and must be a valid date.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $response = Services::substitutionService()->createSubstitution(
            new CreateSubstitutionRequest($timetableEntryId, $substitutionDate, $substituteEmployee),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Get(
        path: '/timetable/substitutions/{id}',
        tags: ['Timetable Substitutions'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/SubstitutionResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::substitutionService()->getSubstitution($id)->toArray());
    }

    #[OA\Get(
        path: '/timetable/entries/{id}/eligible-substitutes',
        tags: ['Timetable Substitutions'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK — employee_ids eligible for this entry\'s subject, and not already booked that day/period (FR-16, Academic Head review step).',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'integer')),
            ),
        ],
    )]
    public function eligibleSubstitutes(int $id)
    {
        return $this->respondSuccess(Services::substitutionService()->listEligibleSubstitutes($id));
    }
}
