<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\HrPayroll\DTOs\CreateDepartmentRequest;
use App\Modules\HrPayroll\DTOs\DepartmentResponse;
use App\Modules\HrPayroll\DTOs\UpdateDepartmentRequest;
use App\Modules\HrPayroll\Entities\Department;
use App\Modules\HrPayroll\Models\DepartmentModel;

/**
 * docs/design/hr-payroll/Phase-3-Service-Controller-Design.md
 * RBAC (ADR-024 §3): master data — writes require `hr_payroll.manage`,
 * reads stay open (unchanged).
 */
class DepartmentService
{
    public function __construct(
        private readonly DepartmentModel $departmentModel,
        private readonly AuditService $auditService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    public function createDepartment(CreateDepartmentRequest $request): DepartmentResponse
    {
        $this->moduleAuthorizer->assertManage(EmployeeService::PERMISSION_MANAGE);

        if ($this->departmentModel->existsByName($request->departmentName)) {
            throw new BusinessRuleException('DEPARTMENT_ALREADY_EXISTS', 'A department with this name already exists.');
        }

        $id = $this->departmentModel->insert(['department_name' => $request->departmentName], true);

        $department = $this->departmentModel->find($id);

        $this->auditService->record('Department', $id, AuditLog::ACTION_CREATE, null, $department->toRawArray());

        return new DepartmentResponse($department);
    }

    public function updateDepartment(int $id, UpdateDepartmentRequest $request): DepartmentResponse
    {
        $this->moduleAuthorizer->assertManage(EmployeeService::PERMISSION_MANAGE);

        $before = $this->requireDepartment($id);

        if ($this->departmentModel->existsByNameExceptId($request->departmentName, $id)) {
            throw new BusinessRuleException('DEPARTMENT_ALREADY_EXISTS', 'A department with this name already exists.');
        }

        $this->departmentModel->update($id, ['department_name' => $request->departmentName]);
        $after = $this->departmentModel->find($id);

        $this->auditService->record('Department', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new DepartmentResponse($after);
    }

    public function getDepartment(int $id): DepartmentResponse
    {
        return new DepartmentResponse($this->requireDepartment($id));
    }

    /**
     * @return list<DepartmentResponse>
     */
    public function listDepartments(): array
    {
        return array_map(
            static fn (Department $department): DepartmentResponse => new DepartmentResponse($department),
            $this->departmentModel->findAll(),
        );
    }

    /**
     * Ungated count for Reports' `getSummary()` dashboard composition —
     * mirrors `EmployeeService::countEmployees()`.
     */
    public function countDepartments(): int
    {
        return $this->departmentModel->countAllResults();
    }

    private function requireDepartment(int $id): Department
    {
        $department = $this->departmentModel->find($id);

        if ($department === null) {
            throw new BusinessRuleException('DEPARTMENT_NOT_FOUND', 'Department not found.');
        }

        return $department;
    }
}
