<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Controllers;

use App\Core\BaseController;
use App\Modules\HrPayroll\Services\LeaveTypeService;
use CodeIgniter\HTTP\ResponseInterface;

class LeaveTypeController extends BaseController
{
    private LeaveTypeService $service;

    public function __construct()
    {
        $this->service = \Config\Services::leaveTypeService();
    }

    /** GET /api/v1/hr-payroll/leave-types?active=1 */
    public function index(): ResponseInterface
    {
        $activeOnly = $this->request->getGet('active') === '1';
        return $this->respondSuccess($this->service->listAll($activeOnly));
    }

    /** GET /api/v1/hr-payroll/leave-types/:id */
    public function show(int $id): ResponseInterface
    {
        $leaveType = $this->service->find($id);
        if ($leaveType === null) {
            return $this->respondError('not_found', 'LEAVE_TYPE_NOT_FOUND', 'Leave type not found', 404);
        }
        return $this->respondSuccess($leaveType);
    }

    /** POST /api/v1/hr-payroll/leave-types */
    public function create(): ResponseInterface
    {
        $data = (array) $this->request->getJSON(true);

        $rules = [
            'code'              => 'required|min_length[1]|max_length[20]|is_unique[leave_types.code]',
            'name'              => 'required|min_length[2]|max_length[100]',
            'description'       => 'permit_empty|max_length[255]',
            'max_days_per_year' => 'permit_empty|integer|greater_than_equal_to[0]',
            'is_paid'           => 'permit_empty|in_list[0,1]',
            'balance_check'     => 'permit_empty|in_list[0,1]',
            'sandwich_rule'     => 'permit_empty|in_list[0,1]',
            'color_hex'         => 'permit_empty|regex_match[/^#[0-9a-fA-F]{6}$/]',
            'sort_order'        => 'permit_empty|integer',
            'is_active'         => 'permit_empty|in_list[0,1]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->respondError('validation', 'VALIDATION_FAILED', 'Validation failed', 422, $this->validator->getErrors());
        }

        $id = $this->service->create($data);
        return $this->respondCreated(['leave_type_id' => $id]);
    }

    /** PATCH /api/v1/hr-payroll/leave-types/:id */
    public function update(int $id): ResponseInterface
    {
        $leaveType = $this->service->find($id);
        if ($leaveType === null) {
            return $this->respondError('not_found', 'LEAVE_TYPE_NOT_FOUND', 'Leave type not found', 404);
        }

        $data = (array) $this->request->getJSON(true);

        $rules = [
            'code'              => "permit_empty|min_length[1]|max_length[20]|is_unique[leave_types.code,leave_type_id,{$id}]",
            'name'              => 'permit_empty|min_length[2]|max_length[100]',
            'description'       => 'permit_empty|max_length[255]',
            'max_days_per_year' => 'permit_empty|integer|greater_than_equal_to[0]',
            'is_paid'           => 'permit_empty|in_list[0,1]',
            'balance_check'     => 'permit_empty|in_list[0,1]',
            'sandwich_rule'     => 'permit_empty|in_list[0,1]',
            'color_hex'         => 'permit_empty|regex_match[/^#[0-9a-fA-F]{6}$/]',
            'sort_order'        => 'permit_empty|integer',
            'is_active'         => 'permit_empty|in_list[0,1]',
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->respondError('validation', 'VALIDATION_FAILED', 'Validation failed', 422, $this->validator->getErrors());
        }

        // Allow explicitly setting sandwich_rule to null (inherit global)
        if (array_key_exists('sandwich_rule', $data) && $data['sandwich_rule'] === null) {
            // keep null — means inherit global
        } elseif (isset($data['sandwich_rule'])) {
            $data['sandwich_rule'] = (int) $data['sandwich_rule'];
        }

        $this->service->update($id, $data);
        return $this->respondSuccess($this->service->find($id));
    }

    /** DELETE /api/v1/hr-payroll/leave-types/:id */
    public function destroy(int $id): ResponseInterface
    {
        $leaveType = $this->service->find($id);
        if ($leaveType === null) {
            return $this->respondError('not_found', 'LEAVE_TYPE_NOT_FOUND', 'Leave type not found', 404);
        }
        $this->service->delete($id);
        return $this->respondSuccess(['message' => 'Leave type deleted']);
    }
}
