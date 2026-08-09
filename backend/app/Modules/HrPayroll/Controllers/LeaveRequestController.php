<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\HrPayroll\DTOs\CreateLeaveRequestRequest;
use App\Modules\HrPayroll\DTOs\DecideLeaveRequestRequest;
use App\Modules\HrPayroll\Entities\LeaveRequest;
use App\Modules\HrPayroll\Models\LeaveTypeModel;
use Config\Services;
use OpenApi\Attributes as OA;

/**
 * docs/design/hr-payroll/Phase-3-Service-Controller-Design.md
 * Base path /api/v1/hr-payroll/leave-requests
 */
#[OA\Tag(name: 'Leave Requests')]
class LeaveRequestController extends BaseController
{
    private const VALID_DECISIONS = [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_REJECTED];

    /** Get valid leave type codes from DB (falls back to empty = no validation if table missing) */
    private function getValidTypes(): array
    {
        try {
            return (new LeaveTypeModel())->getActiveCodes();
        } catch (\Throwable) {
            return []; // no restriction if table not ready yet
        }
    }

    #[OA\Post(
        path: '/hr-payroll/leave-requests',
        tags: ['Leave Requests'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LeaveRequestCreateRequest')),
        responses: [
            new OA\Response(response: 201, description: 'Created.', content: new OA\JsonContent(ref: '#/components/schemas/LeaveRequestResponse')),
            new OA\Response(response: 422, description: 'LEAVE_DATES_OVERLAP.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];

        $employeeId         = (int) ($body['employee_id'] ?? 0);
        $leaveType          = (string) ($body['leave_type'] ?? '');
        $startDate          = (string) ($body['start_date'] ?? '');
        $endDate            = (string) ($body['end_date'] ?? '');
        $reason             = isset($body['reason']) && $body['reason'] !== '' ? (string) $body['reason'] : null;
        $dutyLeaveReference = isset($body['duty_leave_reference']) && $body['duty_leave_reference'] !== '' ? (string) $body['duty_leave_reference'] : null;

        $fields = [];

        if ($employeeId <= 0) {
            $fields['employee_id'] = 'employee_id is required.';
        }

        $validTypes = $this->getValidTypes();
        if ($validTypes !== [] && ! in_array($leaveType, $validTypes, true)) {
            $fields['leave_type'] = 'leave_type must be one of: ' . implode(', ', $validTypes) . '.';
        }

        if ($startDate === '' || $endDate === '' || $endDate < $startDate) {
            $fields['end_date'] = 'start_date and end_date are required and end_date must be >= start_date.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        $response = Services::leaveRequestService()->createLeaveRequest(
            new CreateLeaveRequestRequest($employeeId, $leaveType, $startDate, $endDate, $reason, $dutyLeaveReference),
        );

        return $this->respondCreated($response->toArray());
    }

    #[OA\Post(
        path: '/hr-payroll/leave-requests/{id}/decide',
        tags: ['Leave Requests'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LeaveRequestDecideRequest')),
        responses: [
            new OA\Response(response: 200, description: 'Decided.', content: new OA\JsonContent(ref: '#/components/schemas/LeaveRequestResponse')),
            new OA\Response(response: 422, description: 'INSUFFICIENT_LEAVE_BALANCE.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function decide(int $id)
    {
        $body = $this->request->getJSON(true) ?? [];

        $decision       = (string) ($body['decision'] ?? '');
        $overrideReason = isset($body['override_reason']) && $body['override_reason'] !== '' ? (string) $body['override_reason'] : null;

        if (! in_array($decision, self::VALID_DECISIONS, true)) {
            throw new ValidationException(['decision' => 'decision must be Approved or Rejected.']);
        }

        $response = Services::leaveRequestService()->decide($id, new DecideLeaveRequestRequest($decision, $overrideReason));

        return $this->respondSuccess($response->toArray());
    }

    #[OA\Get(
        path: '/hr-payroll/leave-requests/{id}',
        tags: ['Leave Requests'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK.', content: new OA\JsonContent(ref: '#/components/schemas/LeaveRequestResponse'))],
    )]
    public function show(int $id)
    {
        return $this->respondSuccess(Services::leaveRequestService()->getLeaveRequest($id)->toArray());
    }

    #[OA\Get(
        path: '/hr-payroll/leave-requests',
        tags: ['Leave Requests'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'employee_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/LeaveRequestResponse')),
            ),
        ],
    )]
    public function index()
    {
        $employeeId = (int) ($this->request->getGet('employee_id') ?? 0);
        $status     = (string) ($this->request->getGet('status') ?? '');

        if ($employeeId > 0) {
            $responses = Services::leaveRequestService()->listByEmployee($employeeId);
        } else {
            $responses = Services::leaveRequestService()->listAll($status !== '' ? $status : null);
        }

        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    #[OA\Get(
        path: '/hr-payroll/leave-requests/balance',
        tags: ['Leave Requests'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'employee_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'year', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'BR-HR-004 read-only balance visibility — allocation/consumed/remaining per leave type for the given calendar year.',
            ),
        ],
    )]
    public function balance()
    {
        $employeeId = (int) ($this->request->getGet('employee_id') ?? 0);
        $yearParam  = $this->request->getGet('year');
        $year       = $yearParam !== null && $yearParam !== '' ? (int) $yearParam : (int) date('Y');

        if ($employeeId <= 0) {
            throw new ValidationException(['employee_id' => 'employee_id query parameter is required.']);
        }

        $balances = Services::leaveRequestService()->getBalances($employeeId, $year);

        return $this->respondSuccess(['employee_id' => $employeeId, 'year' => $year, 'balances' => $balances]);
    }

    public function cancel(int $id)
    {
        $body = $this->request->getJSON(true) ?? [];
        $reason = (string) ($body['reason'] ?? '');

        if ($reason === '') {
            throw new ValidationException(['reason' => 'reason is required to cancel leave requests.']);
        }

        $response = Services::leaveRequestService()->cancelLeaveRequest($id, $reason);

        return $this->respondSuccess($response->toArray());
    }
}
