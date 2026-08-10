<?php

declare(strict_types=1);

namespace App\Modules\Administration\Controllers;

use App\Core\BaseController;
use App\Modules\Administration\DTOs\SaveBoardRequest;
use App\Modules\Administration\DTOs\SaveBoardAffiliationRequest;
use App\Modules\Administration\DTOs\SaveAcademicFrameworkRequest;
use Config\Services;

class BoardFrameworkController extends BaseController
{
    // --- BOARD ACTIONS ---

    public function getBoards()
    {
        $boards = Services::boardFrameworkService()->getBoards();
        return $this->respondSuccess(array_map(fn($b) => $b->toArray(), $boards));
    }

    public function createBoard()
    {
        $body = $this->request->getJSON(true) ?? [];
        $request = new SaveBoardRequest(
            name: (string)($body['name'] ?? ''),
            shortName: (string)($body['short_name'] ?? ''),
            boardType: (string)($body['board_type'] ?? ''),
            country: (string)($body['country'] ?? 'India'),
            stateApplicability: isset($body['state_applicability']) ? (string)$body['state_applicability'] : null,
            status: (string)($body['status'] ?? 'ACTIVE'),
            description: isset($body['description']) ? (string)$body['description'] : null
        );

        $response = Services::boardFrameworkService()->saveBoard($request);
        return $this->respondSuccess($response->toArray());
    }

    public function updateBoard(int $id)
    {
        $body = $this->request->getJSON(true) ?? [];
        $request = new SaveBoardRequest(
            name: (string)($body['name'] ?? ''),
            shortName: (string)($body['short_name'] ?? ''),
            boardType: (string)($body['board_type'] ?? ''),
            country: (string)($body['country'] ?? 'India'),
            stateApplicability: isset($body['state_applicability']) ? (string)$body['state_applicability'] : null,
            status: (string)($body['status'] ?? 'ACTIVE'),
            description: isset($body['description']) ? (string)$body['description'] : null
        );

        $response = Services::boardFrameworkService()->saveBoard($request, $id);
        return $this->respondSuccess($response->toArray());
    }

    public function deleteBoard(int $id)
    {
        Services::boardFrameworkService()->deleteBoard($id);
        return $this->respondSuccess(null);
    }

    // --- BOARD AFFILIATION ACTIONS ---

    public function getBoardAffiliations()
    {
        $affiliations = Services::boardFrameworkService()->getBoardAffiliations();
        return $this->respondSuccess(array_map(fn($a) => $a->toArray(), $affiliations));
    }

    public function createBoardAffiliation()
    {
        $body = $this->request->getJSON(true) ?? [];
        $request = new SaveBoardAffiliationRequest(
            boardId: (int)($body['board_id'] ?? 0),
            academicSessionId: (int)($body['academic_session_id'] ?? 0),
            affiliationNumber: (string)($body['affiliation_number'] ?? ''),
            validityStart: isset($body['validity_start']) ? (string)$body['validity_start'] : null,
            validityEnd: isset($body['validity_end']) ? (string)$body['validity_end'] : null,
            status: (string)($body['status'] ?? 'ACTIVE')
        );

        $response = Services::boardFrameworkService()->saveBoardAffiliation($request);
        return $this->respondSuccess($response->toArray());
    }

    public function updateBoardAffiliation(int $id)
    {
        $body = $this->request->getJSON(true) ?? [];
        $request = new SaveBoardAffiliationRequest(
            boardId: (int)($body['board_id'] ?? 0),
            academicSessionId: (int)($body['academic_session_id'] ?? 0),
            affiliationNumber: (string)($body['affiliation_number'] ?? ''),
            validityStart: isset($body['validity_start']) ? (string)$body['validity_start'] : null,
            validityEnd: isset($body['validity_end']) ? (string)$body['validity_end'] : null,
            status: (string)($body['status'] ?? 'ACTIVE')
        );

        $response = Services::boardFrameworkService()->saveBoardAffiliation($request, $id);
        return $this->respondSuccess($response->toArray());
    }

    public function deleteBoardAffiliation(int $id)
    {
        Services::boardFrameworkService()->deleteBoardAffiliation($id);
        return $this->respondSuccess(null);
    }

    // --- ACADEMIC FRAMEWORK ACTIONS ---

    public function getAcademicFrameworks()
    {
        $frameworks = Services::boardFrameworkService()->getAcademicFrameworks();
        return $this->respondSuccess(array_map(fn($f) => $f->toArray(), $frameworks));
    }

    public function getAcademicFramework(int $id)
    {
        $fw = Services::boardFrameworkService()->getAcademicFramework($id);
        return $this->respondSuccess($fw->toArray());
    }

    public function createAcademicFramework()
    {
        $body = $this->request->getJSON(true) ?? [];
        $request = new SaveAcademicFrameworkRequest(
            name: (string)($body['name'] ?? ''),
            boardId: (int)($body['board_id'] ?? 0),
            gradingSchemeId: isset($body['grading_scheme_id']) ? (int)$body['grading_scheme_id'] : null,
            levelDivisions: (array)($body['level_divisions'] ?? []),
            educationalTracks: isset($body['educational_tracks']) ? (array)$body['educational_tracks'] : null,
            passCriteriaJson: isset($body['pass_criteria_json']) ? (array)$body['pass_criteria_json'] : null,
            graceMarksPolicy: isset($body['grace_marks_policy']) ? (array)$body['grace_marks_policy'] : null,
            subjectRequirements: isset($body['subject_requirements']) ? (array)$body['subject_requirements'] : null,
            languageRequirements: isset($body['language_requirements']) ? (array)$body['language_requirements'] : null,
            applicableSessionIds: isset($body['applicable_session_ids']) ? (array)$body['applicable_session_ids'] : null
        );

        $response = Services::boardFrameworkService()->saveAcademicFramework($request);
        return $this->respondSuccess($response->toArray());
    }

    public function updateAcademicFramework(int $id)
    {
        $body = $this->request->getJSON(true) ?? [];
        $request = new SaveAcademicFrameworkRequest(
            name: (string)($body['name'] ?? ''),
            boardId: (int)($body['board_id'] ?? 0),
            gradingSchemeId: isset($body['grading_scheme_id']) ? (int)$body['grading_scheme_id'] : null,
            levelDivisions: (array)($body['level_divisions'] ?? []),
            educationalTracks: isset($body['educational_tracks']) ? (array)$body['educational_tracks'] : null,
            passCriteriaJson: isset($body['pass_criteria_json']) ? (array)$body['pass_criteria_json'] : null,
            graceMarksPolicy: isset($body['grace_marks_policy']) ? (array)$body['grace_marks_policy'] : null,
            subjectRequirements: isset($body['subject_requirements']) ? (array)$body['subject_requirements'] : null,
            languageRequirements: isset($body['language_requirements']) ? (array)$body['language_requirements'] : null,
            applicableSessionIds: isset($body['applicable_session_ids']) ? (array)$body['applicable_session_ids'] : null
        );

        $response = Services::boardFrameworkService()->saveAcademicFramework($request, $id);
        return $this->respondSuccess($response->toArray());
    }

    public function deleteAcademicFramework(int $id)
    {
        Services::boardFrameworkService()->deleteAcademicFramework($id);
        return $this->respondSuccess(null);
    }

    public function submitAcademicFramework(int $id)
    {
        $response = Services::boardFrameworkService()->submitAcademicFramework($id);
        return $this->respondSuccess($response->toArray());
    }

    public function approveAcademicFramework(int $id)
    {
        $response = Services::boardFrameworkService()->approveAcademicFramework($id);
        return $this->respondSuccess($response->toArray());
    }

    public function rejectAcademicFramework(int $id)
    {
        $body = $this->request->getJSON(true) ?? [];
        $reason = (string)($body['reason'] ?? '');
        $response = Services::boardFrameworkService()->rejectAcademicFramework($id, $reason);
        return $this->respondSuccess($response->toArray());
    }
}
