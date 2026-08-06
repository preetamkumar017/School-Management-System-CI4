<?php

declare(strict_types=1);

namespace App\Modules\Timetable\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Timetable\DTOs\CreateTimetableEntryRequest;
use App\Modules\Timetable\Entities\TimetableEntry;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/timetable/Phase-3-Service-Controller-Design.md
 * Base path /api/v1/timetable/entries
 */
#[OA\Tag(name: 'Timetable Entries')]
class TimetableEntryController extends BaseController
{
    private const VALID_DAYS = [
        TimetableEntry::DAY_MONDAY,
        TimetableEntry::DAY_TUESDAY,
        TimetableEntry::DAY_WEDNESDAY,
        TimetableEntry::DAY_THURSDAY,
        TimetableEntry::DAY_FRIDAY,
        TimetableEntry::DAY_SATURDAY,
    ];

    #[OA\Post(
        path: '/timetable/entries',
        tags: ['Timetable Entries'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/TimetableEntryRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/TimetableEntryResponse')),
            new OA\Response(response: 422, description: 'TEACHER_DOUBLE_BOOKED, SECTION_DOUBLE_BOOKED, ROOM_DOUBLE_BOOKED, or WEEKLY_LOAD_CEILING_EXCEEDED.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $request = $this->buildRequest($this->request->getJSON(true) ?? []);

        $response = Services::timetableEntryService()->createEntry($request);

        return $this->respondCreated($response->toArray());
    }

    #[OA\Post(
        path: '/timetable/entries/{id}/publish',
        tags: ['Timetable Entries'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'DRAFT -> PUBLISHED.', content: new OA\JsonContent(ref: '#/components/schemas/TimetableEntryResponse'))],
    )]
    public function publish(int $id)
    {
        $response = Services::timetableEntryService()->publishEntry($id);

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Post(
        path: '/timetable/entries/{id}/revise',
        tags: ['Timetable Entries'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/TimetableEntryRequest')),
        responses: [
            new OA\Response(response: 200, description: 'BR-TT-005 revision — increments version_no.', content: new OA\JsonContent(ref: '#/components/schemas/TimetableEntryResponse')),
            new OA\Response(response: 422, description: 'TIMETABLE_ENTRY_NOT_PUBLISHED.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function revise(int $id)
    {
        $request = $this->buildRequest($this->request->getJSON(true) ?? []);

        $response = Services::timetableEntryService()->reviseEntry($id, $request);

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Get(
        path: '/timetable/entries/{id}',
        tags: ['Timetable Entries'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/TimetableEntryResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::timetableEntryService()->getEntry($id)->toArray());
    }

    #[OA\Get(
        path: '/timetable/entries',
        tags: ['Timetable Entries'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'section_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK — a section\'s weekly schedule.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/TimetableEntryResponse')),
            ),
        ],
    )]
    public function index()
    {
        $sectionId = (int) ($this->request->getGet('section_id') ?? 0);

        if ($sectionId <= 0) {
            throw new ValidationException(['section_id' => 'section_id query parameter is required.']);
        }

        $responses = Services::timetableEntryService()->listEntriesBySection($sectionId);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function buildRequest(array $body): CreateTimetableEntryRequest
    {
        $sectionId  = (int) ($body['section_id'] ?? 0);
        $subjectId  = (int) ($body['subject_id'] ?? 0);
        $employeeId = (int) ($body['employee_id'] ?? 0);
        $dayOfWeek  = (string) ($body['day_of_week'] ?? '');
        $periodNo   = (int) ($body['period_no'] ?? 0);
        $roomId     = isset($body['room_id']) && $body['room_id'] !== '' ? (string) $body['room_id'] : null;

        $fields = [];

        if ($sectionId <= 0) {
            $fields['section_id'] = 'section_id is required.';
        }

        if ($subjectId <= 0) {
            $fields['subject_id'] = 'subject_id is required.';
        }

        if ($employeeId <= 0) {
            $fields['employee_id'] = 'employee_id is required.';
        }

        if (! in_array($dayOfWeek, self::VALID_DAYS, true)) {
            $fields['day_of_week'] = 'day_of_week must be one of MONDAY through SATURDAY.';
        }

        if ($periodNo <= 0) {
            $fields['period_no'] = 'period_no is required and must be a positive integer.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        return new CreateTimetableEntryRequest($sectionId, $subjectId, $employeeId, $dayOfWeek, $periodNo, $roomId);
    }
}
