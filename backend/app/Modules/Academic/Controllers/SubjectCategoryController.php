<?php

declare(strict_types=1);

namespace App\Modules\Academic\Controllers;

use App\Core\BaseController;
use App\Core\Exceptions\ValidationException;
use App\Modules\Academic\DTOs\CreateSubjectCategoryRequest;
use App\Modules\Academic\DTOs\UpdateSubjectCategoryRequest;
use Config\Services;

class SubjectCategoryController extends BaseController
{
    public function create()
    {
        [$categoryName, $categoryCode, $description] = $this->validateFields($this->request->getJSON(true) ?? []);

        $response = Services::subjectCategoryService()->createCategory(
            new CreateSubjectCategoryRequest($categoryName, $categoryCode, $description)
        );

        return $this->respondCreated($response->toArray());
    }

    public function update(int $id)
    {
        $body = $this->request->getJSON(true) ?? [];
        [$categoryName, $categoryCode, $description] = $this->validateFields($body);
        $isActive = isset($body['is_active']) ? (int) $body['is_active'] : 1;

        $response = Services::subjectCategoryService()->updateCategory(
            $id,
            new UpdateSubjectCategoryRequest($categoryName, $categoryCode, $description, $isActive)
        );

        return $this->respondSuccess($response->toArray());
    }

    public function delete(int $id)
    {
        Services::subjectCategoryService()->deleteCategory($id);
        return $this->respondSuccess(null, [], 204);
    }

    public function show(int $id)
    {
        return $this->respondSuccess(Services::subjectCategoryService()->getCategory($id)->toArray());
    }

    public function index()
    {
        $responses = Services::subjectCategoryService()->listCategories();
        return $this->respondSuccess(array_map(static fn ($response) => $response->toArray(), $responses));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: string, 1: string, 2: ?string}
     */
    private function validateFields(array $body): array
    {
        $categoryName = trim((string) ($body['category_name'] ?? ''));
        $categoryCode = trim((string) ($body['category_code'] ?? ''));
        $description  = isset($body['description']) ? trim((string) $body['description']) : null;
        $fields       = [];

        if ($categoryName === '' || strlen($categoryName) > 50) {
            $fields['category_name'] = 'category_name is required and must be at most 50 characters.';
        }

        if ($categoryCode === '' || strlen($categoryCode) > 20) {
            $fields['category_code'] = 'category_code is required and must be at most 20 characters.';
        }

        if ($fields !== []) {
            throw new ValidationException($fields);
        }

        return [$categoryName, $categoryCode, $description];
    }
}
