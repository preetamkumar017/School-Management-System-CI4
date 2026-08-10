<?php

declare(strict_types=1);

namespace App\Modules\Academic\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Academic\DTOs\CreateSubjectRequest;
use App\Modules\Academic\DTOs\UpdateSubjectRequest;
use Config\Services;

class SubjectController extends BaseController
{
    public function create()
    {
        $body = $this->request->getJSON(true) ?? [];
        [$subjectName, $subjectCode, $categoryId, $isLanguage, $stream] = $this->validateFields($body);

        $response = Services::subjectService()->createSubject(
            new CreateSubjectRequest($subjectName, $subjectCode, $categoryId, $isLanguage, $stream)
        );

        return $this->respondCreated($response->toArray());
    }

    public function update(int $id)
    {
        $body = $this->request->getJSON(true) ?? [];
        [$subjectName, $subjectCode, $categoryId, $isLanguage, $stream] = $this->validateFields($body);

        $response = Services::subjectService()->updateSubject(
            $id,
            new UpdateSubjectRequest($subjectName, $subjectCode, $categoryId, $isLanguage, $stream)
        );

        return $this->respondSuccess($response->toArray());
    }

    public function delete(int $id)
    {
        Services::subjectService()->deleteSubject($id);
        return $this->respondSuccess(null, [], 204);
    }

    public function show(int $id)
    {
        return $this->respondSuccess(Services::subjectService()->getSubject($id)->toArray());
    }

    public function index()
    {
        $responses = Services::subjectService()->listSubjects();
        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: string, 1: string, 2: ?int, 3: int, 4: string}
     */
    private function validateFields(array $body): array
    {
        $subjectName = trim((string) ($body['subject_name'] ?? ''));
        $subjectCode = trim((string) ($body['subject_code'] ?? ''));
        $categoryId  = isset($body['subject_category_id']) && $body['subject_category_id'] !== '' ? (int) $body['subject_category_id'] : null;
        $isLanguage  = isset($body['is_language_subject']) ? (int) $body['is_language_subject'] : 0;
        $stream      = trim((string) ($body['stream_applicability'] ?? 'ALL'));
        $fields      = [];

        if ($subjectName === '') {
            $fields['subject_name'] = 'subject_name is required.';
        }

        if ($subjectCode === '' || strlen($subjectCode) > 10) {
            $fields['subject_code'] = 'subject_code is required and must be at most 10 characters.';
        }

        if (!in_array($stream, ['ALL', 'SCIENCE', 'COMMERCE', 'ARTS', 'NONE'], true)) {
            $fields['stream_applicability'] = 'stream_applicability is invalid.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        return [$subjectName, $subjectCode, $categoryId, $isLanguage, $stream];
    }
}
