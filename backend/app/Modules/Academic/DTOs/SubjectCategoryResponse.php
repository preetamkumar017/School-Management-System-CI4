<?php

declare(strict_types=1);

namespace App\Modules\Academic\DTOs;

use App\Modules\Academic\Entities\SubjectCategory;

final class SubjectCategoryResponse
{
    public readonly int $subjectCategoryId;
    public readonly string $categoryName;
    public readonly string $categoryCode;
    public readonly ?string $description;
    public readonly int $isActive;

    public function __construct(SubjectCategory $category)
    {
        $this->subjectCategoryId = $category->subject_category_id;
        $this->categoryName      = $category->category_name;
        $this->categoryCode      = $category->category_code;
        $this->description       = $category->description;
        $this->isActive          = $category->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'subject_category_id' => $this->subjectCategoryId,
            'category_name'        => $this->categoryName,
            'category_code'        => $this->categoryCode,
            'description'          => $this->description,
            'is_active'            => $this->isActive,
        ];
    }
}
