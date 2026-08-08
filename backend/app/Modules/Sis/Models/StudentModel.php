<?php

declare(strict_types=1);

namespace App\Modules\Sis\Models;

use App\Core\BaseModel;
use App\Modules\Sis\Entities\Student;

/**
 * docs/design/sis/Phase-4.3-Repository-Design.md
 */
class StudentModel extends BaseModel
{
    protected $table          = 'students';
    protected $primaryKey     = 'student_id';
    protected $returnType     = Student::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'admission_number',
        'full_name',
        'dob',
        'aadhaar_number',
        'section_id',
        'application_id',
        'category',
        'medical_info',
        'status',
        'photo_document_id',
        'created_by',
        'updated_by',
    ];

    public function findByAdmissionNumber(string $value): ?Student
    {
        return $this->where('admission_number', $value)->first();
    }

    public function existsByAdmissionNumber(string $value): bool
    {
        return $this->where('admission_number', $value)->countAllResults() > 0;
    }

    public function existsByAdmissionNumberExceptId(string $value, int $id): bool
    {
        return $this->where('admission_number', $value)->where('student_id !=', $id)->countAllResults() > 0;
    }

    public function existsByAadhaarNumber(string $value): bool
    {
        return $this->where('aadhaar_number', $value)->countAllResults() > 0;
    }

    public function existsByAadhaarNumberExceptId(string $value, int $id): bool
    {
        return $this->where('aadhaar_number', $value)->where('student_id !=', $id)->countAllResults() > 0;
    }

    public function existsByApplicationId(int $applicationId): bool
    {
        return $this->where('application_id', $applicationId)->countAllResults() > 0;
    }

    /**
     * @return list<Student>
     */
    public function findBySectionId(int $sectionId): array
    {
        return $this->where('section_id', $sectionId)->findAll();
    }

    public function countBySectionIdAndStatus(int $sectionId, string $status): int
    {
        return $this->where('section_id', $sectionId)->where('status', $status)->countAllResults();
    }

    /**
     * @return list<Student>
     */
    public function findByStatus(string $status): array
    {
        return $this->where('status', $status)->findAll();
    }
}
