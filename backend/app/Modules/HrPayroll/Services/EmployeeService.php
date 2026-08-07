<?php

declare(strict_types=1);

namespace App\Modules\HrPayroll\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Administration\DTOs\UserStatusChangeRequest;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Entities\User;
use App\Modules\Administration\Models\UserModel;
use App\Modules\Administration\Services\AuditService;
use App\Modules\HrPayroll\DTOs\CreateEmployeeRequest;
use App\Modules\HrPayroll\DTOs\EmployeeResponse;
use App\Modules\HrPayroll\DTOs\UpdateEmployeeRequest;
use App\Modules\HrPayroll\Entities\Employee;
use App\Modules\HrPayroll\Models\AttendanceClosureModel;
use App\Modules\HrPayroll\Models\DepartmentModel;
use App\Modules\HrPayroll\Models\DesignationModel;
use App\Modules\HrPayroll\Models\EmployeeModel;
use CodeIgniter\I18n\Time;
use Config\Database;
use Config\Services as AppServices;

/**
 * docs/design/hr-payroll/Phase-3-Service-Controller-Design.md
 */
class EmployeeService
{
    public function __construct(
        private readonly EmployeeModel $employeeModel,
        private readonly DepartmentModel $departmentModel,
        private readonly DesignationModel $designationModel,
        private readonly AttendanceClosureModel $attendanceClosureModel,
        private readonly AuditService $auditService,
    ) {
    }

    public function createEmployee(CreateEmployeeRequest $request): EmployeeResponse
    {
        if ($this->departmentModel->find($request->departmentId) === null) {
            throw new BusinessRuleException('DEPARTMENT_NOT_FOUND', 'Department not found.');
        }

        if ($this->designationModel->find($request->designationId) === null) {
            throw new BusinessRuleException('DESIGNATION_NOT_FOUND', 'Designation not found.');
        }

        if ($this->employeeModel->existsByCode($request->employeeCode)) {
            throw new BusinessRuleException('EMPLOYEE_CODE_ALREADY_EXISTS', 'An employee with this code already exists.');
        }

        $id = $this->employeeModel->insert([
            'employee_code'          => $request->employeeCode,
            'full_name'              => $request->fullName,
            'department_id'          => $request->departmentId,
            'designation_id'         => $request->designationId,
            'joining_date'           => $request->joiningDate,
            'salary_structure_json'  => $request->salaryStructureJson,
            'status'                 => Employee::STATUS_ACTIVE,
        ], true);

        $employee = $this->employeeModel->find($id);

        $this->auditService->record('Employee', $id, AuditLog::ACTION_CREATE, null, $employee->toRawArray());

        return new EmployeeResponse($employee);
    }

    /**
     * BR-HR-002 (ADR-008 §5): setting exit_date deactivates the linked
     * User in the same transaction — if deactivation fails, the whole
     * update rolls back (fail closed, no async SLA).
     */
    public function updateEmployee(int $id, UpdateEmployeeRequest $request): EmployeeResponse
    {
        $before = $this->requireEmployee($id);

        if ($this->departmentModel->find($request->departmentId) === null) {
            throw new BusinessRuleException('DEPARTMENT_NOT_FOUND', 'Department not found.');
        }

        if ($this->designationModel->find($request->designationId) === null) {
            throw new BusinessRuleException('DESIGNATION_NOT_FOUND', 'Designation not found.');
        }

        $isExiting = $before->exit_date === null && $request->exitDate !== null;

        $db = Database::connect();
        $db->transStart();

        $data = [
            'full_name'             => $request->fullName,
            'department_id'         => $request->departmentId,
            'designation_id'        => $request->designationId,
            'salary_structure_json' => $request->salaryStructureJson,
        ];

        if ($request->exitDate !== null) {
            $data['exit_date'] = $request->exitDate;
            $data['status']    = Employee::STATUS_EXITED;
        }

        $this->employeeModel->update($id, $data);

        if ($isExiting) {
            $this->revokeAccess($id);
        }

        $after = $this->employeeModel->find($id);

        $this->auditService->record('Employee', $id, AuditLog::ACTION_UPDATE, $before->toRawArray(), $after->toRawArray());

        $db->transComplete();

        if ($db->transStatus() === false) {
            throw new BusinessRuleException('EMPLOYEE_UPDATE_FAILED', 'Employee update failed and was rolled back.');
        }

        return new EmployeeResponse($after);
    }

    public function getEmployee(int $id): EmployeeResponse
    {
        return new EmployeeResponse($this->requireEmployee($id));
    }

    /**
     * @return list<EmployeeResponse>
     */
    public function listEmployees(): array
    {
        return array_map(
            static fn (Employee $employee): EmployeeResponse => new EmployeeResponse($employee),
            $this->employeeModel->findAll(),
        );
    }

    /**
     * Written only by Attendance's StaffAttendanceService::closePeriod()
     * (ADR-008 §4's one-way push) — HR & Payroll never calls into
     * Attendance to check this.
     */
    public function recordAttendanceClosure(int $employeeId, string $payPeriod, int $closedBy): void
    {
        if ($this->employeeModel->find($employeeId) === null) {
            throw new BusinessRuleException('EMPLOYEE_NOT_FOUND', 'Employee not found.');
        }

        if ($this->attendanceClosureModel->existsByEmployeePeriod($employeeId, $payPeriod)) {
            return;
        }

        $this->attendanceClosureModel->insert([
            'employee_id' => $employeeId,
            'pay_period'  => $payPeriod,
            'closed_at'   => Time::now()->toDateTimeString(),
            'closed_by'   => $closedBy,
        ]);
    }

    private function revokeAccess(int $employeeId): void
    {
        $userModel = new UserModel();
        $user      = $userModel->findByOwner('EMPLOYEE', $employeeId);

        if ($user === null) {
            return;
        }

        AppServices::userService()->changeStatus($user->user_id, new UserStatusChangeRequest(User::STATUS_DEACTIVATED));
    }

    private function requireEmployee(int $id): Employee
    {
        $employee = $this->employeeModel->find($id);

        if ($employee === null) {
            throw new BusinessRuleException('EMPLOYEE_NOT_FOUND', 'Employee not found.');
        }

        return $employee;
    }
}
