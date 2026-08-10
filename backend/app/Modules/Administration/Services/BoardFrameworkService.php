<?php

declare(strict_types=1);

namespace App\Modules\Administration\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\ValidationException;
use App\Modules\Administration\DTOs\BoardResponse;
use App\Modules\Administration\DTOs\BoardAffiliationResponse;
use App\Modules\Administration\DTOs\AcademicFrameworkResponse;
use App\Modules\Administration\DTOs\SaveBoardRequest;
use App\Modules\Administration\DTOs\SaveBoardAffiliationRequest;
use App\Modules\Administration\DTOs\SaveAcademicFrameworkRequest;
use App\Modules\Administration\Entities\Board;
use App\Modules\Administration\Entities\BoardAffiliation;
use App\Modules\Administration\Entities\AcademicFramework;
use App\Modules\Administration\Entities\FrameworkSessionMapping;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Models\BoardModel;
use App\Modules\Administration\Models\BoardAffiliationModel;
use App\Modules\Administration\Models\AcademicFrameworkModel;
use App\Modules\Administration\Models\FrameworkSessionMappingModel;
use App\Modules\Administration\Models\UserModel;
use App\Modules\HrPayroll\Models\EmployeeModel;
use App\Modules\HrPayroll\Models\DesignationModel;
use App\Modules\Academic\Models\AcademicSessionModel;
use App\Modules\Academic\Models\GradingSchemeModel;
use App\Core\Http\RequestContext;
use Config\Services;

class BoardFrameworkService
{
    public function __construct(
        private readonly BoardModel $boardModel,
        private readonly BoardAffiliationModel $boardAffiliationModel,
        private readonly AcademicFrameworkModel $academicFrameworkModel,
        private readonly FrameworkSessionMappingModel $frameworkSessionMappingModel,
        private readonly AuditService $auditService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
        private readonly EmployeeModel $employeeModel,
        private readonly DesignationModel $designationModel,
        private readonly UserModel $userModel,
        private readonly AcademicSessionModel $academicSessionModel,
        private readonly GradingSchemeModel $gradingSchemeModel
    ) {}

    // --- BOARD MASTER METHODS ---

    public function saveBoard(SaveBoardRequest $request, ?int $boardId = null): BoardResponse
    {
        $this->moduleAuthorizer->assertManage('administration.manage');

        $errors = [];
        if (trim($request->name) === '') {
            $errors['name'] = 'Board name is required.';
        }
        if (trim($request->shortName) === '') {
            $errors['short_name'] = 'Short name / abbreviation is required.';
        }
        if (trim($request->boardType) === '') {
            $errors['board_type'] = 'Board type is required.';
        }

        // Uniqueness checks
        if ($boardId === null) {
            if ($this->boardModel->where('name', $request->name)->where('is_deleted', false)->countAllResults() > 0) {
                $errors['name'] = 'A board with this name already exists.';
            }
            if ($this->boardModel->where('short_name', $request->shortName)->where('is_deleted', false)->countAllResults() > 0) {
                $errors['short_name'] = 'A board with this abbreviation already exists.';
            }
        } else {
            if ($this->boardModel->where('name', $request->name)->where('board_id !=', $boardId)->where('is_deleted', false)->countAllResults() > 0) {
                $errors['name'] = 'A board with this name already exists.';
            }
            if ($this->boardModel->where('short_name', $request->shortName)->where('board_id !=', $boardId)->where('is_deleted', false)->countAllResults() > 0) {
                $errors['short_name'] = 'A board with this abbreviation already exists.';
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        $data = [
            'name'                => $request->name,
            'short_name'          => $request->shortName,
            'board_type'          => $request->boardType,
            'country'             => $request->country,
            'state_applicability' => $request->stateApplicability,
            'status'              => $request->status,
            'description'         => $request->description,
        ];

        if ($boardId === null) {
            $boardId = $this->boardModel->insert($data);
            $board = $this->boardModel->find($boardId);
            $this->auditService->record('geo_boards', (int)$boardId, AuditLog::ACTION_CREATE, null, $board->toRawArray());
        } else {
            $existing = $this->boardModel->find($boardId);
            $before = $existing->toRawArray();
            $this->boardModel->update($boardId, $data);
            $board = $this->boardModel->find($boardId);
            $this->auditService->record('geo_boards', (int)$boardId, AuditLog::ACTION_UPDATE, $before, $board->toRawArray());
        }

        return new BoardResponse($board);
    }

    public function deleteBoard(int $boardId): void
    {
        $this->moduleAuthorizer->assertManage('administration.manage');

        $existing = $this->boardModel->find($boardId);
        if ($existing) {
            // Check references
            if ($this->boardAffiliationModel->where('board_id', $boardId)->where('is_deleted', false)->countAllResults() > 0) {
                throw new ValidationException(['board_id' => 'Cannot delete board as it has active session affiliations.']);
            }
            if ($this->academicFrameworkModel->where('board_id', $boardId)->where('is_deleted', false)->countAllResults() > 0) {
                throw new ValidationException(['board_id' => 'Cannot delete board as it is linked to academic frameworks.']);
            }

            $before = $existing->toRawArray();
            $this->boardModel->delete($boardId);
            $this->auditService->record('geo_boards', $boardId, AuditLog::ACTION_DELETE, $before, null);
        }
    }

    public function getBoards(): array
    {
        $this->moduleAuthorizer->assertManage('administration.manage');
        $boards = $this->boardModel->where('is_deleted', false)->findAll();
        return array_map(fn($b) => new BoardResponse($b), $boards);
    }

    // --- BOARD AFFILIATION METHODS ---

    public function saveBoardAffiliation(SaveBoardAffiliationRequest $request, ?int $affiliationId = null): BoardAffiliationResponse
    {
        $this->moduleAuthorizer->assertManage('administration.manage');

        $errors = [];
        if ($request->boardId <= 0) {
            $errors['board_id'] = 'Valid Board reference is required.';
        } else {
            $board = $this->boardModel->find($request->boardId);
            if ($board === null || $board->is_deleted) {
                $errors['board_id'] = 'Selected Board does not exist.';
            }
        }

        if ($request->academicSessionId <= 0) {
            $errors['academic_session_id'] = 'Valid Academic Session reference is required.';
        } else {
            $session = $this->academicSessionModel->find($request->academicSessionId);
            if ($session === null || $session->is_deleted) {
                $errors['academic_session_id'] = 'Selected Academic Session does not exist.';
            }
        }

        if (trim($request->affiliationNumber) === '') {
            $errors['affiliation_number'] = 'Affiliation number is required.';
        }

        // Uniqueness check: One active board per session
        if ($affiliationId === null) {
            if ($this->boardAffiliationModel->where('academic_session_id', $request->academicSessionId)->where('is_deleted', false)->countAllResults() > 0) {
                $errors['academic_session_id'] = 'This Academic Session already has an active board affiliation mapped.';
            }
        } else {
            if ($this->boardAffiliationModel->where('academic_session_id', $request->academicSessionId)->where('affiliation_id !=', $affiliationId)->where('is_deleted', false)->countAllResults() > 0) {
                $errors['academic_session_id'] = 'This Academic Session already has an active board affiliation mapped.';
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        $data = [
            'board_id'            => $request->boardId,
            'academic_session_id' => $request->academicSessionId,
            'affiliation_number'  => $request->affiliationNumber,
            'validity_start'      => $request->validityStart ? date('Y-m-d', strtotime($request->validityStart)) : null,
            'validity_end'        => $request->validityEnd ? date('Y-m-d', strtotime($request->validityEnd)) : null,
            'status'              => $request->status,
        ];

        if ($affiliationId === null) {
            $affiliationId = $this->boardAffiliationModel->insert($data);
            $aff = $this->boardAffiliationModel->find($affiliationId);
            $this->auditService->record('board_affiliations', (int)$affiliationId, AuditLog::ACTION_CREATE, null, $aff->toRawArray());
        } else {
            $existing = $this->boardAffiliationModel->find($affiliationId);
            $before = $existing->toRawArray();
            $this->boardAffiliationModel->update($affiliationId, $data);
            $aff = $this->boardAffiliationModel->find($affiliationId);
            $this->auditService->record('board_affiliations', (int)$affiliationId, AuditLog::ACTION_UPDATE, $before, $aff->toRawArray());
        }

        $boardName = $this->boardModel->find($aff->board_id)?->name;
        $sessionName = $this->academicSessionModel->find($aff->academic_session_id)?->session_name;

        return new BoardAffiliationResponse($aff, $boardName, $sessionName);
    }

    public function deleteBoardAffiliation(int $affiliationId): void
    {
        $this->moduleAuthorizer->assertManage('administration.manage');

        $existing = $this->boardAffiliationModel->find($affiliationId);
        if ($existing) {
            $before = $existing->toRawArray();
            $this->boardAffiliationModel->delete($affiliationId);
            $this->auditService->record('board_affiliations', $affiliationId, AuditLog::ACTION_DELETE, $before, null);
        }
    }

    public function getBoardAffiliations(): array
    {
        $this->moduleAuthorizer->assertManage('administration.manage');
        $affs = $this->boardAffiliationModel->where('is_deleted', false)->findAll();

        return array_map(function($aff) {
            $boardName = $this->boardModel->find($aff->board_id)?->name;
            $sessionName = $this->academicSessionModel->find($aff->academic_session_id)?->session_name;
            return new BoardAffiliationResponse($aff, $boardName, $sessionName);
        }, $affs);
    }

    // --- ACADEMIC FRAMEWORK METHODS ---

    public function getAcademicFrameworks(): array
    {
        $this->moduleAuthorizer->assertManage('administration.manage');
        $fws = $this->academicFrameworkModel->where('is_deleted', false)->findAll();

        return array_map(function($fw) {
            $boardName = $this->boardModel->find($fw->board_id)?->name;
            $gradingSchemeName = $fw->grading_scheme_id ? $this->gradingSchemeModel->find($fw->grading_scheme_id)?->scheme_name : null;
            $sessionIds = array_map(
                fn($m) => (int)$m->academic_session_id,
                $this->frameworkSessionMappingModel->where('framework_id', $fw->framework_id)->where('is_deleted', false)->findAll()
            );
            return new AcademicFrameworkResponse($fw, $boardName, $gradingSchemeName, $sessionIds);
        }, $fws);
    }

    public function getAcademicFramework(int $frameworkId): AcademicFrameworkResponse
    {
        $this->moduleAuthorizer->assertManage('administration.manage');
        $fw = $this->academicFrameworkModel->find($frameworkId);
        if ($fw === null || $fw->is_deleted) {
            throw new ValidationException(['framework_id' => 'Academic framework does not exist.']);
        }

        $boardName = $this->boardModel->find($fw->board_id)?->name;
        $gradingSchemeName = $fw->grading_scheme_id ? $this->gradingSchemeModel->find($fw->grading_scheme_id)?->scheme_name : null;
        $sessionIds = array_map(
            fn($m) => (int)$m->academic_session_id,
            $this->frameworkSessionMappingModel->where('framework_id', $fw->framework_id)->where('is_deleted', false)->findAll()
        );

        return new AcademicFrameworkResponse($fw, $boardName, $gradingSchemeName, $sessionIds);
    }

    public function saveAcademicFramework(SaveAcademicFrameworkRequest $request, ?int $frameworkId = null): AcademicFrameworkResponse
    {
        $this->moduleAuthorizer->assertManage('administration.manage');

        $errors = [];
        if (trim($request->name) === '') {
            $errors['name'] = 'Framework name is required.';
        }
        if ($request->boardId <= 0 || $this->boardModel->find($request->boardId) === null) {
            $errors['board_id'] = 'Valid Board reference is required.';
        }
        if (empty($request->levelDivisions)) {
            $errors['level_divisions'] = 'At least one Level Division is required.';
        }

        // Immutable checks
        if ($frameworkId !== null) {
            $existing = $this->academicFrameworkModel->find($frameworkId);
            if ($existing->approval_status === 'PUBLISHED') {
                throw new ValidationException(['framework_status' => 'Published framework versions are immutable. Modify by creating a new version.']);
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        $data = [
            'name'                 => $request->name,
            'board_id'             => $request->boardId,
            'grading_scheme_id'    => $request->gradingSchemeId,
            'level_divisions'      => $request->levelDivisions,
            'educational_tracks'   => $request->educationalTracks,
            'pass_criteria_json'   => $request->passCriteriaJson,
            'grace_marks_policy'   => $request->graceMarksPolicy,
            'subject_requirements' => $request->subjectRequirements,
            'language_requirements'=> $request->languageRequirements,
        ];

        if ($frameworkId === null) {
            $data['approval_status'] = 'DRAFT';
            $data['version'] = 1;
            $frameworkId = $this->academicFrameworkModel->insert($data);
            $fw = $this->academicFrameworkModel->find($frameworkId);
            $this->auditService->record('academic_frameworks', (int)$frameworkId, AuditLog::ACTION_CREATE, null, $fw->toRawArray());
        } else {
            $existing = $this->academicFrameworkModel->find($frameworkId);
            $before = $existing->toRawArray();
            $this->academicFrameworkModel->update($frameworkId, $data);
            $fw = $this->academicFrameworkModel->find($frameworkId);
            $this->auditService->record('academic_frameworks', (int)$frameworkId, AuditLog::ACTION_UPDATE, $before, $fw->toRawArray());
        }

        // Handle session mapping updates (if in draft/submitted mode)
        if ($request->applicableSessionIds !== null) {
            $this->frameworkSessionMappingModel->where('framework_id', $frameworkId)->delete();
            foreach ($request->applicableSessionIds as $sessId) {
                $this->frameworkSessionMappingModel->insert([
                    'framework_id'        => $frameworkId,
                    'academic_session_id' => $sessId,
                ]);
            }
        }

        $boardName = $this->boardModel->find($fw->board_id)?->name;
        $gradingSchemeName = $fw->grading_scheme_id ? $this->gradingSchemeModel->find($fw->grading_scheme_id)?->scheme_name : null;
        $sessionIds = array_map(
            fn($m) => (int)$m->academic_session_id,
            $this->frameworkSessionMappingModel->where('framework_id', $fw->framework_id)->where('is_deleted', false)->findAll()
        );

        return new AcademicFrameworkResponse($fw, $boardName, $gradingSchemeName, $sessionIds);
    }

    public function submitAcademicFramework(int $frameworkId): AcademicFrameworkResponse
    {
        $this->moduleAuthorizer->assertManage('administration.manage');

        $existing = $this->academicFrameworkModel->find($frameworkId);
        if ($existing === null || $existing->is_deleted) {
            throw new ValidationException(['framework_id' => 'Academic framework does not exist.']);
        }

        if ($existing->approval_status !== 'DRAFT') {
            throw new ValidationException(['framework_id' => 'Only draft frameworks can be submitted for approval.']);
        }

        $before = $existing->toRawArray();
        $this->academicFrameworkModel->update($frameworkId, ['approval_status' => 'SUBMITTED']);
        $fw = $this->academicFrameworkModel->find($frameworkId);
        $this->auditService->record('academic_frameworks', $frameworkId, 'UPDATE', $before, $fw->toRawArray());

        return $this->getAcademicFramework($frameworkId);
    }

    public function approveAcademicFramework(int $frameworkId): AcademicFrameworkResponse
    {
        $userId = (int)RequestContext::userId();
        $this->verifyApproverPrivilege($userId);

        $existing = $this->academicFrameworkModel->find($frameworkId);
        if ($existing === null || $existing->is_deleted) {
            throw new ValidationException(['framework_id' => 'Academic framework does not exist.']);
        }

        if ($existing->approval_status !== 'SUBMITTED') {
            throw new ValidationException(['framework_id' => 'Only submitted frameworks can be approved.']);
        }

        // Maker-Checker validation
        if ((int)$existing->created_by === $userId) {
            throw new ValidationException(['maker_checker' => 'The creator of the framework version cannot approve or reject it.']);
        }

        $before = $existing->toRawArray();
        $this->academicFrameworkModel->update($frameworkId, [
            'approval_status' => 'PUBLISHED',
            'approved_by'      => $userId,
            'approved_at'      => date('Y-m-d H:i:s')
        ]);

        $fw = $this->academicFrameworkModel->find($frameworkId);
        $this->auditService->record('academic_frameworks', $frameworkId, 'APPROVE', $before, $fw->toRawArray());

        return $this->getAcademicFramework($frameworkId);
    }

    public function rejectAcademicFramework(int $frameworkId, string $reason): AcademicFrameworkResponse
    {
        $userId = (int)RequestContext::userId();
        $this->verifyApproverPrivilege($userId);

        if (trim($reason) === '' || strlen(trim($reason)) < 10) {
            throw new ValidationException(['reason' => 'A valid rejection reason (minimum 10 characters) is required.']);
        }

        $existing = $this->academicFrameworkModel->find($frameworkId);
        if ($existing === null || $existing->is_deleted) {
            throw new ValidationException(['framework_id' => 'Academic framework does not exist.']);
        }

        if ($existing->approval_status !== 'SUBMITTED') {
            throw new ValidationException(['framework_id' => 'Only submitted frameworks can be rejected.']);
        }

        // Maker-Checker validation
        if ((int)$existing->created_by === $userId) {
            throw new ValidationException(['maker_checker' => 'The creator of the framework version cannot approve or reject it.']);
        }

        $before = $existing->toRawArray();
        $this->academicFrameworkModel->update($frameworkId, [
            'approval_status'  => 'REJECTED',
            'rejection_reason' => $reason
        ]);

        $fw = $this->academicFrameworkModel->find($frameworkId);
        $this->auditService->record('academic_frameworks', $frameworkId, 'REJECT', $before, $fw->toRawArray());

        return $this->getAcademicFramework($frameworkId);
    }

    public function deleteAcademicFramework(int $frameworkId): void
    {
        $this->moduleAuthorizer->assertManage('administration.manage');

        $existing = $this->academicFrameworkModel->find($frameworkId);
        if ($existing) {
            if ($existing->approval_status === 'PUBLISHED') {
                throw new ValidationException(['framework_id' => 'Cannot delete a published academic framework version.']);
            }
            $before = $existing->toRawArray();
            $this->academicFrameworkModel->delete($frameworkId);
            $this->frameworkSessionMappingModel->where('framework_id', $frameworkId)->delete();
            $this->auditService->record('academic_frameworks', $frameworkId, AuditLog::ACTION_DELETE, $before, null);
        }
    }

    // --- PRIVILEGES & CONFIG CONTROLS ---

    private function verifyApproverPrivilege(int $userId): void
    {
        $user = $this->userModel->find($userId);
        if ($user === null) {
            throw new ValidationException(['role' => 'User record not found.']);
        }

        // If the user's role grants global admin override, let's verify if they have the proper designation
        if ($user->owner_type !== 'EMPLOYEE') {
            throw new ValidationException(['role' => 'Only employee accounts are authorized to perform academic framework review tasks.']);
        }

        $employee = $this->employeeModel->find($user->owner_ref_id);
        if ($employee === null) {
            throw new ValidationException(['role' => 'Employee profile not found.']);
        }

        $designation = $this->designationModel->find($employee->designation_id);
        if ($designation === null) {
            throw new ValidationException(['role' => 'Designation not resolved.']);
        }

        $primaryTarget = Services::configurationService()->getString('administration.board_approver_designation');
        $altTarget = Services::configurationService()->getString('administration.board_alternate_approver_designation');

        $designationName = strtolower(trim($designation->designation_name));

        if ($designationName !== strtolower(trim($primaryTarget)) && $designationName !== strtolower(trim($altTarget))) {
            throw new ValidationException(['role' => "Your designation ({$designation->designation_name}) is not authorized to approve board frameworks."]);
        }
    }
}
