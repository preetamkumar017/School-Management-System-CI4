<?php

declare(strict_types=1);

namespace Tests\Feature\HrPayroll;

use App\Modules\Administration\Models\UserModel;
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
}
