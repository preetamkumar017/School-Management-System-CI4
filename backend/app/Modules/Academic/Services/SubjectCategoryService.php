<?php

declare(strict_types=1);

namespace App\Modules\Academic\Services;

use App\Core\Authz\ModuleAuthorizer;
use App\Core\Exceptions\BusinessRuleException;
use App\Modules\Academic\DTOs\CreateSubjectCategoryRequest;
use App\Modules\Academic\DTOs\SubjectCategoryResponse;
use App\Modules\Academic\DTOs\UpdateSubjectCategoryRequest;
use App\Modules\Academic\Entities\SubjectCategory;
use App\Modules\Academic\Models\SubjectCategoryModel;
use App\Modules\Academic\Models\SubjectModel;
use App\Modules\Administration\Entities\AuditLog;
use App\Modules\Administration\Services\AuditService;

class SubjectCategoryService
{
    public const PERMISSION_MANAGE = 'academic.manage';

    public function __construct(
        private readonly SubjectCategoryModel $subjectCategoryModel,
        private readonly SubjectModel $subjectModel,
        private readonly AuditService $auditService,
        private readonly ModuleAuthorizer $moduleAuthorizer,
    ) {
    }

    public function createCategory(CreateSubjectCategoryRequest $request): SubjectCategoryResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        if ($this->subjectCategoryModel->existsByCode($request->categoryCode)) {
            throw new BusinessRuleException('SUBJECT_CATEGORY_CODE_ALREADY_TAKEN', 'This category code is already taken.');
        }

        $id = $this->subjectCategoryModel->insert([
            'category_name' => $request->categoryName,
            'category_code' => $request->categoryCode,
            'description'   => $request->description,
            'is_active'     => 1,
        ], true);

        $category = $this->subjectCategoryModel->find($id);

        $this->auditService->record('SubjectCategory', $id, AuditLog::ACTION_CREATE, null, $category->toRawArray());

        return new SubjectCategoryResponse($category);
    }

    public function updateCategory(int $id, UpdateSubjectCategoryRequest $request): SubjectCategoryResponse
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireCategory($id);

        if ($this->subjectCategoryModel->existsByCodeExceptId($request->categoryCode, $id)) {
            throw new BusinessRuleException('SUBJECT_CATEGORY_CODE_ALREADY_TAKEN', 'This category code is already taken.');
        }

        // Operational reference check on deactivation
        if ($request->isActive === 0 && $before->is_active === 1) {
            $this->assertNoSubjectReferences($id);
        }

        $this->subjectCategoryModel->update($id, [
            'category_name' => $request->categoryName,
            'category_code' => $request->categoryCode,
            'description'   => $request->description,
            'is_active'     => $request->isActive,
        ]);

        $after = $this->subjectCategoryModel->find($id);

        $this->auditService->record(
            'SubjectCategory',
            $id,
            AuditLog::ACTION_UPDATE,
            $before->toRawArray(),
            $after->toRawArray(),
        );

        return new SubjectCategoryResponse($after);
    }

    public function deleteCategory(int $id): void
    {
        $this->moduleAuthorizer->assertManage(self::PERMISSION_MANAGE);

        $before = $this->requireCategory($id);

        $this->assertNoSubjectReferences($id);

        $this->subjectCategoryModel->delete($id);

        $this->auditService->record('SubjectCategory', $id, AuditLog::ACTION_DELETE, $before->toRawArray(), null);
    }

    public function getCategory(int $id): SubjectCategoryResponse
    {
        return new SubjectCategoryResponse($this->requireCategory($id));
    }

    /**
     * @return list<SubjectCategoryResponse>
     */
    public function listCategories(): array
    {
        return array_map(
            static fn (SubjectCategory $category): SubjectCategoryResponse => new SubjectCategoryResponse($category),
            $this->subjectCategoryModel->findAll(),
        );
    }

    private function requireCategory(int $id): SubjectCategory
    {
        $category = $this->subjectCategoryModel->find($id);

        if ($category === null) {
            throw new BusinessRuleException('SUBJECT_CATEGORY_NOT_FOUND', 'Subject category not found.');
        }

        return $category;
    }

    private function assertNoSubjectReferences(int $id): void
    {
        if ($this->subjectModel->where('subject_category_id', $id)->countAllResults() > 0) {
            throw new BusinessRuleException(
                'SUBJECT_CATEGORY_HAS_ACTIVE_REFERENCES',
                'This category is currently referenced by subjects and cannot be deleted or deactivated.',
            );
        }
    }
}
