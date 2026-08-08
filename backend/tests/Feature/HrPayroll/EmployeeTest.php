<?php

declare(strict_types=1);

namespace Tests\Feature\HrPayroll;

use App\Core\Exceptions\AuthorizationException;
use App\Modules\Administration\Models\UserModel;
use App\Modules\HrPayroll\Services\EmployeeService;
use Tests\Support\HrPayroll\HrPayrollTestCase;

/**
 * @internal
 */
final class EmployeeTest extends HrPayrollTestCase
{
    public function testCreateEmployee(): void
    {
        $user          = $this->createUser();
        $tokens        = $this->loginAs($user['username']);
        $headers       = $this->authHeaders($tokens['access_token']);
        $departmentId  = $this->createDepartmentFixture();
        $designationId = $this->createDesignationFixture();

        $response = $this->withHeaders($headers)->withBodyFormat('json')->post('api/v1/hr-payroll/employees', [
            'employee_code'         => 'EMP-001',
            'full_name'             => 'Priya Verma',
            'department_id'         => $departmentId,
            'designation_id'        => $designationId,
            'joining_date'          => '2022-06-01',
            'salary_structure_json' => ['basic' => 40000],
        ]);

        $response->assertStatus(201);
        $body = $this->decode($response)['data'];
        $this->assertSame('EMP-001', $body['employee_code']);
        $this->assertSame('Active', $body['status']);
    }

    /**
     * BR-HR-002 (ADR-008 §5): setting exit_date deactivates the linked
     * User account in the same transaction.
     */
    public function testSettingExitDateRevokesLinkedUserAccess(): void
    {
        $user          = $this->createUser();
        $tokens        = $this->loginAs($user['username']);
        $headers       = $this->authHeaders($tokens['access_token']);
        // createUser() hardcodes owner_ref_id=1 for the acting/auth user
        // (Administration has no real Employee to point at) — this
        // throwaway employee consumes id 1 so the real fixture below
        // can't collide with it.
        $this->createEmployeeFixture();
        $employeeId    = $this->createEmployeeFixture();
        $departmentId  = $this->createDepartmentFixture();
        $designationId = $this->createDesignationFixture();

        $linkedUserId = (new UserModel())->insert([
            'username'      => 'staff_' . uniqid('', true),
            'password_hash' => password_hash('Test@1234', PASSWORD_BCRYPT),
            'role_id'       => $user['role_id'],
            'owner_type'    => 'EMPLOYEE',
            'owner_ref_id'  => $employeeId,
            'status'        => 'ACTIVE',
        ], true);

        $response = $this->withHeaders($headers)->withBodyFormat('json')->patch("api/v1/hr-payroll/employees/{$employeeId}", [
            'full_name'             => 'Priya Verma',
            'department_id'         => $departmentId,
            'designation_id'        => $designationId,
            'salary_structure_json' => ['basic' => 42000],
            'exit_date'             => '2026-08-07',
        ]);

        $response->assertStatus(200);
        $body = $this->decode($response)['data'];
        $this->assertSame('Exited', $body['status']);

        $linkedUser = (new UserModel())->find($linkedUserId);
        $this->assertSame('DEACTIVATED', $linkedUser->status);
    }

    /**
     * ADR-024 §3 + this phase's Addendum — the exploit demonstrated
     * 2026-08-08: a caller without `hr_payroll.manage` could PATCH any
     * employee's full profile and salary, including their OWN record.
     * `updateEmployee()` is deliberately Tier-1-only (no ownership
     * fallback, even for self) because every field on
     * `UpdateEmployeeRequest` (full_name, department_id, designation_id,
     * salary_structure_json, exit_date) is HR-administrative, not safe
     * for self-service editing.
     */
    public function testUpdateEmployeeRejectedForCallerWithoutManagePermissionEvenForOwnRecord(): void
    {
        $employeeId    = $this->createEmployeeFixture();
        $departmentId  = $this->createDepartmentFixture();
        $designationId = $this->createDesignationFixture();

        // A restricted, generic-permission-only role — no
        // hr_payroll.manage — whose linked User owns this exact employee
        // record, proving even self-ownership doesn't unlock this write.
        $roleId = $this->createRole(['read']);
        $userId = (new UserModel())->insert([
            'username'      => 'self_' . uniqid('', true),
            'password_hash' => password_hash(self::TEST_PASSWORD, PASSWORD_BCRYPT),
            'role_id'       => $roleId,
            'owner_type'    => 'EMPLOYEE',
            'owner_ref_id'  => $employeeId,
            'status'        => 'ACTIVE',
        ], true);
        $username = (new UserModel())->find($userId)->username;

        $tokens  = $this->loginAs($username);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->withBodyFormat('json')->patch("api/v1/hr-payroll/employees/{$employeeId}", [
                'full_name'             => 'Self Promoted',
                'department_id'         => $departmentId,
                'designation_id'        => $designationId,
                'salary_structure_json' => ['basic' => 999999],
            ]),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );
    }

    /**
     * A caller carrying `hr_payroll.manage` may update any employee's
     * record — Tier 1 success path.
     */
    public function testUpdateEmployeeSucceedsForCallerWithManagePermission(): void
    {
        $employeeId    = $this->createEmployeeFixture();
        $departmentId  = $this->createDepartmentFixture();
        $designationId = $this->createDesignationFixture();

        $roleId  = $this->createRole([EmployeeService::PERMISSION_MANAGE]);
        $user    = $this->createUser($roleId);
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $response = $this->withHeaders($headers)->withBodyFormat('json')->patch("api/v1/hr-payroll/employees/{$employeeId}", [
            'full_name'             => 'Updated By HR',
            'department_id'         => $departmentId,
            'designation_id'        => $designationId,
            'salary_structure_json' => ['basic' => 45000],
        ]);

        $response->assertStatus(200);
        $this->assertSame('Updated By HR', $this->decode($response)['data']['full_name']);
    }

    /**
     * `getEmployee()` (single-record read) allows Tier 2 — reading your
     * own profile is safe self-service, unlike `updateEmployee()` above.
     */
    public function testGetEmployeeSucceedsForOwnRecordWithoutManagePermission(): void
    {
        $employeeId = $this->createEmployeeFixture();

        $roleId = $this->createRole(['read']);
        $userId = (new UserModel())->insert([
            'username'      => 'self_' . uniqid('', true),
            'password_hash' => password_hash(self::TEST_PASSWORD, PASSWORD_BCRYPT),
            'role_id'       => $roleId,
            'owner_type'    => 'EMPLOYEE',
            'owner_ref_id'  => $employeeId,
            'status'        => 'ACTIVE',
        ], true);
        $username = (new UserModel())->find($userId)->username;

        $tokens  = $this->loginAs($username);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->withHeaders($headers)->get("api/v1/hr-payroll/employees/{$employeeId}")->assertStatus(200);
    }

    /**
     * `listEmployees()` is Tier-1-only — listing every employee is not
     * self-service for anyone, even an employee reading their own record
     * fine via `getEmployee()`.
     */
    public function testListEmployeesRejectedWithoutManagePermission(): void
    {
        $this->createEmployeeFixture();

        $user    = $this->createUser($this->createRole(['read']));
        $tokens  = $this->loginAs($user['username']);
        $headers = $this->authHeaders($tokens['access_token']);

        $this->assertApiException(
            fn () => $this->withHeaders($headers)->get('api/v1/hr-payroll/employees'),
            AuthorizationException::class,
            'NOT_AUTHORIZED',
            403,
        );
    }
}
