<?php

declare(strict_types=1);

namespace Tests\Feature\Administration;

use App\Modules\Administration\Models\BoardModel;
use App\Modules\Administration\Models\BoardAffiliationModel;
use App\Modules\Administration\Models\AcademicFrameworkModel;
use App\Modules\Administration\Models\FrameworkSessionMappingModel;
use App\Modules\HrPayroll\Models\EmployeeModel;
use App\Modules\HrPayroll\Models\DesignationModel;
use App\Modules\Administration\Models\UserModel;
use App\Modules\Academic\Models\AcademicSessionModel;
use App\Modules\Academic\Models\GradingSchemeModel;
use Tests\Support\Administration\AdministrationTestCase;
use App\Core\Exceptions\ValidationException;

class BoardFrameworkTest extends AdministrationTestCase
{
    protected $headers;
    private array $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = $this->createUser();
        $tokens = $this->loginAs($this->adminUser['username']);
        $this->headers = $this->authHeaders($tokens['access_token']);
    }

    public function testBoardCrudAndValidation(): void
    {
        // 1. Create Board
        $res = $this->withHeaders($this->headers)->withBodyFormat('json')->post('api/v1/administration/boards', [
            'name'                => 'Central Board of Secondary Education',
            'short_name'          => 'CBSE',
            'board_type'          => 'CENTRAL',
            'country'             => 'India',
            'state_applicability' => null,
            'status'              => 'ACTIVE',
            'description'         => 'National level school board'
        ]);

        $res->assertStatus(200);
        $body = $this->decode($res);
        $this->assertEquals('Central Board of Secondary Education', $body['data']['name']);
        $boardId = $body['data']['board_id'];

        // 2. Validate Duplicate Name (should trigger 422 ValidationException)
        $this->assertApiException(
            fn () => $this->withHeaders($this->headers)->withBodyFormat('json')->post('api/v1/administration/boards', [
                'name'       => 'Central Board of Secondary Education',
                'short_name' => 'CBSE-ALT',
                'board_type' => 'CENTRAL'
            ]),
            ValidationException::class,
            'VALIDATION_FAILED',
            422
        );

        // 3. Update Board
        $resUpdate = $this->withHeaders($this->headers)->withBodyFormat('json')->patch("api/v1/administration/boards/{$boardId}", [
            'name'                => 'Central Board of Secondary Education Updated',
            'short_name'          => 'CBSE',
            'board_type'          => 'CENTRAL',
            'country'             => 'India',
            'status'              => 'ACTIVE'
        ]);
        $resUpdate->assertStatus(200);

        // 4. Delete Board
        $resDel = $this->withHeaders($this->headers)->delete("api/v1/administration/boards/{$boardId}");
        $resDel->assertStatus(200);
    }

    public function testBoardAffiliationUniquenessPerSession(): void
    {
        // Setup board & session
        $boardId = (new BoardModel())->insert([
            'name'       => 'State Board of Chhattisgarh',
            'short_name' => 'CGBSE',
            'board_type' => 'STATE'
        ]);
        $sessId = (new AcademicSessionModel())->insert([
            'session_name' => '2026-27-BF-TEST',
            'start_date'   => '2026-06-01',
            'end_date'     => '2027-04-30',
            'status'       => 'ACTIVE'
        ]);

        // 1. Create Affiliation
        $res = $this->withHeaders($this->headers)->withBodyFormat('json')->post('api/v1/administration/board-affiliations', [
            'board_id'            => $boardId,
            'academic_session_id' => $sessId,
            'affiliation_number'  => 'CGBSE-100203',
            'validity_start'      => '2026-06-01',
            'validity_end'        => '2030-06-01'
        ]);
        $res->assertStatus(200);

        // 2. Validate Duplicate Affiliation per Session
        $this->assertApiException(
            fn () => $this->withHeaders($this->headers)->withBodyFormat('json')->post('api/v1/administration/board-affiliations', [
                'board_id'            => $boardId,
                'academic_session_id' => $sessId,
                'affiliation_number'  => 'CGBSE-ALT'
            ]),
            ValidationException::class,
            'VALIDATION_FAILED',
            422
        );
    }

    public function testAcademicFrameworkWorkflowAndMakerChecker(): void
    {
        $boardId = (new BoardModel())->insert([
            'name'       => 'Indian School Board',
            'short_name' => 'ISB',
            'board_type' => 'OTHER'
        ]);

        // Create grading scheme
        $schemeId = (new GradingSchemeModel())->insert([
            'scheme_name' => 'ISB Primary Scale',
            'board_type'  => 'CBSE',
            'grade_band_json' => []
        ]);

        // 1. Create Academic Framework
        $res = $this->withHeaders($this->headers)->withBodyFormat('json')->post('api/v1/administration/academic-frameworks', [
            'name'                 => 'ISB Primary Framework',
            'board_id'             => $boardId,
            'grading_scheme_id'    => $schemeId,
            'level_divisions'      => ['Primary', 'Middle'],
            'educational_tracks'   => null,
            'pass_criteria_json'   => ['subject_pass_percentage' => 33, 'overall_pass_percentage' => 35],
            'grace_marks_policy'   => ['max_grace_marks' => 5, 'rounding_policy' => 'Round before grace calculation'],
            'subject_requirements' => ['min_mandatory_subjects' => 5],
            'language_requirements'=> ['mandatory_languages_count' => 1]
        ]);
        $res->assertStatus(200);
        $body = $this->decode($res);
        $fwId = $body['data']['framework_id'];

        // Verify status is DRAFT
        $this->assertEquals('DRAFT', $body['data']['approval_status']);

        // 2. Submit Framework
        $resSubmit = $this->withHeaders($this->headers)->post("api/v1/administration/academic-frameworks/{$fwId}/submit");
        $resSubmit->assertStatus(200);
        $bodySubmit = $this->decode($resSubmit);
        $this->assertEquals('SUBMITTED', $bodySubmit['data']['approval_status']);

        // 3. Maker-Checker Self-Approval Block
        // Maker is the current admin user (adminUser['user_id']). Approving with same headers should throw maker-checker exception.
        $this->assertApiException(
            fn () => $this->withHeaders($this->headers)->post("api/v1/administration/academic-frameworks/{$fwId}/approve"),
            ValidationException::class,
            'VALIDATION_FAILED',
            422
        );

        // 4. Verification of Alternate Approver Fallback
        // Setup another user who is a Principal
        $desigId = (new DesignationModel())->insert(['designation_name' => 'Principal']);
        $deptId = $this->db->table('departments')->insert(['department_name' => 'Academics ' . uniqid('', true)], true);
        $empId = (new EmployeeModel())->insert([
            'employee_code'   => 'EMP-PRINCIPAL',
            'full_name'       => 'Dr. Alice',
            'department_id'   => $deptId,
            'designation_id'  => $desigId,
            'joining_date'    => '2020-01-01',
            'salary_structure_json' => []
        ]);
        $approverUserId = (new UserModel())->insert([
            'username'      => 'principal_user',
            'password_hash' => password_hash(self::TEST_PASSWORD, PASSWORD_BCRYPT),
            'role_id'       => 1, // Admin role
            'owner_type'    => 'EMPLOYEE',
            'owner_ref_id'  => $empId
        ]);

        // Generate alternate headers for checker user
        $tokensChecker = $this->loginAs('principal_user');
        $checkerHeaders = $this->authHeaders($tokensChecker['access_token']);

        // 5. Approve with checker headers
        $resApprove = $this->withHeaders($checkerHeaders)->post("api/v1/administration/academic-frameworks/{$fwId}/approve");
        $resApprove->assertStatus(200);
        $bodyApprove = $this->decode($resApprove);
        $this->assertEquals('PUBLISHED', $bodyApprove['data']['approval_status']);

        // 6. Test Immutability
        $this->assertApiException(
            fn () => $this->withHeaders($this->headers)->withBodyFormat('json')->patch("api/v1/administration/academic-frameworks/{$fwId}", [
                'name'     => 'Modified Immutable Framework',
                'board_id' => $boardId,
                'level_divisions' => ['Primary']
            ]),
            ValidationException::class,
            'VALIDATION_FAILED',
            422
        );
    }
}
