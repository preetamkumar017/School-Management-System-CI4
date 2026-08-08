<?php

declare(strict_types=1);

namespace App\Modules\Sis\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/sis/Phase-4.2-Domain-Model.md — ENT-SIS-001 (Revision 2).
 *
 * @property int|null    $student_id
 * @property string      $admission_number
 * @property string      $full_name
 * @property string      $dob
 * @property string|null $aadhaar_number
 * @property int|null    $section_id
 * @property int         $application_id
 * @property string      $category
 * @property string|null $medical_info
 * @property string      $status
 * @property int|null    $photo_document_id
 */
class Student extends BaseEntity
{
    public const CATEGORY_GENERAL = 'GENERAL';
    public const CATEGORY_RTE     = 'RTE';

    public const STATUS_DRAFT    = 'DRAFT';
    public const STATUS_ACTIVE   = 'ACTIVE';
    public const STATUS_PROMOTED = 'PROMOTED';
    public const STATUS_EXITED   = 'EXITED';
    public const STATUS_ARCHIVED = 'ARCHIVED';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'student_id'         => 'integer',
            'section_id'         => '?integer',
            'application_id'     => 'integer',
            'photo_document_id'  => '?integer',
        ]);

        parent::__construct($data);
    }
}
