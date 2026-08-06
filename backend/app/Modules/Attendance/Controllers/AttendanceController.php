<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Attendance\DTOs\AttendanceCorrectionRequest;
use App\Modules\Attendance\DTOs\CreateAttendanceRecordRequest;
use App\Modules\Attendance\Entities\AttendanceRecord;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/attendance/Phase-3-Service-Controller-Design.md
 * Base path /api/v1/attendance/records
 */
#[OA\Tag(name: 'Attendance')]
class AttendanceController extends BaseController
{
    private const VALID_STATES = [
        AttendanceRecord::STATE_PRESENT,
        AttendanceRecord::STATE_ABSENT,
        AttendanceRecord::STATE_LATE,
    ];

    #[OA\Post(
        path: '/attendance/records',
        tags: ['Attendance'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/AttendanceRecordCreateRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/AttendanceRecordResponse')),
            new OA\Response(response: 422, description: 'ATTENDANCE_ALREADY_MARKED or TIMETABLE_ENTRY_NOT_PUBLISHED.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body               = $this->request->getJSON(true) ?? [];
        $studentId          = (int) ($body['student_id'] ?? 0);
        $timetableEntryId   = (int) ($body['timetable_entry_id'] ?? 0);
        $attendanceDate     = (string) ($body['attendance_date'] ?? '');
        $state              = (string) ($body['state'] ?? '');

        $fields = [];

        if ($studentId <= 0) {
            $fields['student_id'] = 'student_id is required.';
        }

        if ($timetableEntryId <= 0) {
            $fields['timetable_entry_id'] = 'timetable_entry_id is required.';
        }

        if (strtotime($attendanceDate) === false) {
            $fields['attendance_date'] = 'attendance_date is required and must be a valid date.';
        }

        if (! in_array($state, self::VALID_STATES, true)) {
            $fields['state'] = 'state must be one of PRESENT, ABSENT, LATE.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $response = Services::attendanceService()->markAttendance(
            new CreateAttendanceRecordRequest($studentId, $timetableEntryId, $attendanceDate, $state),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Post(
        path: '/attendance/records/{id}/lock',
        tags: ['Attendance'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'BR-ATT-002 lock.', content: new OA\JsonContent(ref: '#/components/schemas/AttendanceRecordResponse'))],
    )]
    public function lock(int $id)
    {
        $response = Services::attendanceService()->lockAttendance($id);

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Post(
        path: '/attendance/records/{id}/correct',
        tags: ['Attendance'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['state'],
                properties: [
                    new OA\Property(property: 'state', type: 'string', enum: ['PRESENT', 'ABSENT', 'LATE']),
                    new OA\Property(property: 'reason', type: 'string', nullable: true, description: 'Required once past the same-day edit window (BR-ATT-003).'),
                ],
            ),
        ),
        responses: [new OA\Response(response: 200, description: 'BR-ATT-002/003 correction.', content: new OA\JsonContent(ref: '#/components/schemas/AttendanceRecordResponse'))],
    )]
    public function correct(int $id)
    {
        $body   = $this->request->getJSON(true) ?? [];
        $state  = (string) ($body['state'] ?? '');
        $reason = isset($body['reason']) && $body['reason'] !== '' ? (string) $body['reason'] : null;

        if (! in_array($state, self::VALID_STATES, true)) {
            throw new ValidationException(['state' => 'state must be one of PRESENT, ABSENT, LATE.']);
        }

        $response = Services::attendanceService()->correctAttendance($id, new AttendanceCorrectionRequest($state, $reason));

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Get(
        path: '/attendance/records/{id}',
        tags: ['Attendance'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/AttendanceRecordResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::attendanceService()->getAttendanceRecord($id)->toArray());
    }

    #[OA\Get(
        path: '/attendance/records',
        tags: ['Attendance'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'timetable_entry_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK — the period\'s marked roster.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/AttendanceRecordResponse')),
            ),
        ],
    )]
    public function index()
    {
        $timetableEntryId = (int) ($this->request->getGet('timetable_entry_id') ?? 0);
        $date              = (string) ($this->request->getGet('date') ?? '');

        $fields = [];

        if ($timetableEntryId <= 0) {
            $fields['timetable_entry_id'] = 'timetable_entry_id query parameter is required.';
        }

        if (strtotime($date) === false) {
            $fields['date'] = 'date query parameter is required and must be a valid date.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $responses = Services::attendanceService()->listByTimetableEntryAndDate($timetableEntryId, $date);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    #[OA\Get(
        path: '/attendance/records/percentage',
        tags: ['Attendance'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'student_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'from_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'FR-13 / BR-ATT-006.', content: new OA\JsonContent(ref: '#/components/schemas/AttendancePercentageResponse')),
        ],
    )]
    public function percentage()
    {
        $studentId = (int) ($this->request->getGet('student_id') ?? 0);
        $fromDate  = (string) ($this->request->getGet('from_date') ?? '');
        $toDate    = (string) ($this->request->getGet('to_date') ?? '');

        $fields = [];

        if ($studentId <= 0) {
            $fields['student_id'] = 'student_id query parameter is required.';
        }

        if (strtotime($fromDate) === false) {
            $fields['from_date'] = 'from_date query parameter is required and must be a valid date.';
        }

        if (strtotime($toDate) === false) {
            $fields['to_date'] = 'to_date query parameter is required and must be a valid date.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $response = Services::attendanceService()->calculateAttendancePercentage($studentId, $fromDate, $toDate);

        return $this->respondSuccess($response->toArray());
    }
}
