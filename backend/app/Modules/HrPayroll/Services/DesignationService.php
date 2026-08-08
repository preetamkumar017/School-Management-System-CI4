<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;
use App\Modules\HrPayroll\DTOs\CreateDesignationRequest;
use App\Modules\HrPayroll\DTOs\DesignationResponse;
use App\Modules\HrPayroll\DTOs\UpdateDesignationRequest;
use App\Modules\HrPayroll\Entities\Designation;
use App\Modules\HrPayroll\Models\DesignationModel;

/**
 * docs/design/hr-payroll/Phase-3-Service-Controller-Design.md
 * RBAC (ADR-024 §3): master data — writes require `hr_payroll.manage`,
 * reads stay open (unchanged).
 */
class DesignationService
{
    public function __construct(
        private readonly DesignationModel $designationModel,
        private readonly AuditService $auditService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    public function createDesignation(CreateDesignationRequest $request): DesignationResponse
    {
        $this->moduleAuthorizer->assertManage(EmployeeService::PERMISSION_MANAGE);

        if ($this->designationModel->existsByName($request->designationName)) {
            throw new BusinessRuleException('DESIGNATION_ALREADY_EXISTS', 'A designation with this name already exists.');
        }

        $id = $this->designationModel->insert(['designation_name' => $request->designationName], true);

        $designation = $this->designationModel->find($id);

        $this->auditService->record('Designation', $id, AuditLog::ACTION_CREATE, null, $designation->toRawArray());

        return new DesignationResponse($designation);
    }

    public function updateDesignation(int $id, UpdateDesignationRequest $request): DesignationResponse
    {
        $this->moduleAuthorizer->assertManage(EmployeeService::PERMISSION_MANAGE);

        $before = $this->requireDesignation($id);

        if ($this->designationModel->existsByNameExceptId($request->designationName, $id)) {
            throw new BusinessRuleException('DESIGNATION_ALREADY_EXISTS', 'A designation with this name already exists.');
        }

        $this->designationModel->update($id, ['designation_name' => $request->designationName]);
        $after = $this->designationModel->find($id);

        $this->auditService->record('Designation', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        return new DesignationResponse($after);
    }

    public function getDesignation(int $id): DesignationResponse
    {
        return new DesignationResponse($this->requireDesignation($id));
    }

    /**
     * @return list<DesignationResponse>
     */
    public function listDesignations(): array
    {
        return array_map(
            static fn (Designation $designation): DesignationResponse => new DesignationResponse($designation),
            $this->designationModel->findAll(),
        );
    }

    /**
     * Ungated count for Reports' `getSummary()` dashboard composition —
     * mirrors `EmployeeService::countEmployees()`.
     */
    public function countDesignations(): int
    {
        return $this->designationModel->countAllResults();
    }

    private function requireDesignation(int $id): Designation
    {
        $designation = $this->designationModel->find($id);

        if ($designation === null) {
            throw new BusinessRuleException('DESIGNATION_NOT_FOUND', 'Designation not found.');
        }

        return $designation;
    }
}
