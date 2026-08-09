<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Attendance\DTOs\CreateStaffAttendanceRecordRequest;
use App\Modules\Attendance\Entities\StaffAttendanceRecord;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/hr-payroll/Phase-3-Service-Controller-Design.md (ADR-008 §3)
 * Base path /api/v1/attendance/staff-attendance
 */
#[OA\Tag(name: 'Staff Attendance')]
class StaffAttendanceRecordController extends BaseController
{
    private const VALID_STATES = [
        StaffAttendanceRecord::STATE_PRESENT,
        StaffAttendanceRecord::STATE_ON_LEAVE,
        StaffAttendanceRecord::STATE_UNAUTHORIZED,
    ];

    #[OA\Post(
        path: '/attendance/staff-attendance',
        tags: ['Staff Attendance'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StaffAttendanceRecordCreateRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/StaffAttendanceRecordResponse')),
            new OA\Response(response: 422, description: 'STAFF_ATTENDANCE_ALREADY_RECORDED.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];

        $employeeId = (int) ($body['employee_id'] ?? 0);
        $date       = (string) ($body['attendance_date'] ?? '');
        $state      = (string) ($body['state'] ?? '');

        $fields = [];

        if ($employeeId <= 0) {
            $fields['employee_id'] = 'employee_id is required.';
        }

        if ($date === '') {
            $fields['attendance_date'] = 'attendance_date is required.';
        }

        if (! in_array($state, self::VALID_STATES, true)) {
            $fields['state'] = 'state must be one of Present, On Leave, Unauthorized.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $response = Services::staffAttendanceService()->recordAttendance(
            new CreateStaffAttendanceRecordRequest($employeeId, $date, $state),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Post(
        path: '/attendance/staff-attendance/reconcile',
        tags: ['Staff Attendance'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StaffAttendanceReconcileRequest')),
        responses: [new OA\Response(response: 200, description: 'Reconciled (BR-ATT-005).')],
    )]
    public function reconcile()
    {
        $body = $this->request->getJSON(true) ?? [];

        $employeeId = (int) ($body['employee_id'] ?? 0);
        $fromDate   = (string) ($body['from_date'] ?? '');
        $toDate     = (string) ($body['to_date'] ?? '');

        if ($employeeId <= 0 || $fromDate === '' || $toDate === '') {
            throw new ValidationException(['employee_id' => 'employee_id, from_date, and to_date are required.']);
        }

        Services::staffAttendanceService()->reconcile($employeeId, $fromDate, $toDate);

        return $this->respondSuccess(['reconciled' => true]);
    }

    #[OA\Post(
        path: '/attendance/staff-attendance/close-period',
        tags: ['Staff Attendance'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StaffAttendanceClosePeriodRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Closed (BR-HR-001).'),
            new OA\Response(response: 422, description: 'STAFF_ATTENDANCE_NOT_RECONCILED.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function closePeriod()
    {
        $body = $this->request->getJSON(true) ?? [];

        $employeeId = (int) ($body['employee_id'] ?? 0);
        $payPeriod  = (string) ($body['pay_period'] ?? '');

        if ($employeeId <= 0 || preg_match('/^\d{4}-\d{2}$/', $payPeriod) !== 1) {
            throw new ValidationException(['pay_period' => 'employee_id is required and pay_period must be in YYYY-MM format.']);
        }

        Services::staffAttendanceService()->closePeriod($employeeId, $payPeriod);

        return $this->respondSuccess(['closed' => true]);
    }

    #[OA\Get(
        path: '/attendance/staff-attendance',
        tags: ['Staff Attendance'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'employee_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'from_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to_date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/StaffAttendanceRecordResponse')),
            ),
        ],
    )]
    public function index()
    {
        $employeeId = (int) ($this->request->getGet('employee_id') ?? 0);
        $fromDate   = (string) ($this->request->getGet('from_date') ?? '');
        $toDate     = (string) ($this->request->getGet('to_date') ?? '');

        if ($employeeId <= 0 || $fromDate === '' || $toDate === '') {
            throw new ValidationException(['employee_id' => 'employee_id, from_date, and to_date query parameters are required.']);
        }

        $responses = Services::staffAttendanceService()->listByEmployeeBetween($employeeId, $fromDate, $toDate);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    #[OA\Get(
        path: '/attendance/staff-attendance/daily',
        tags: ['Staff Attendance'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/StaffAttendanceRecordResponse')),
            ),
        ],
    )]
    public function daily()
    {
        $date = (string) ($this->request->getGet('date') ?? '');

        if ($date === '') {
            throw new ValidationException(['date' => 'date query parameter is required.']);
        }

        $responses = Services::staffAttendanceService()->listByDate($date);

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }
}
