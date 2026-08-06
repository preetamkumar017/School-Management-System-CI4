<?php

declare(strict_types=1);

namespace App\Modules\Admission\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/admission/Phase-1-Domain-Model.md — ENT-ADM-001.
 * The SUBMITTED/VERIFIED/... -> ADMITTED transition is FR-02 Confirm
 * Enrollment (docs/design/admission/Phase-6), implemented alongside SIS
 * in Stage 5 — not part of this entity's Stage 4 scope.
 *
 * @property int|null    $application_id
 * @property string      $application_reference_no
 * @property string      $applicant_name
 * @property string      $dob
 * @property int         $class_applied_id
 * @property string|null $aadhaar_number
 * @property string      $category
 * @property string      $status
 * @property \CodeIgniter\I18n\Time $submitted_at
 * @property \CodeIgniter\I18n\Time|null $decided_at
 */
class Application extends BaseEntity
{
    public const CATEGORY_GENERAL = 'GENERAL';
    public const CATEGORY_RTE     = 'RTE';

    public const STATUS_SUBMITTED   = 'SUBMITTED';
    public const STATUS_VERIFIED    = 'VERIFIED';
    public const STATUS_SHORTLISTED = 'SHORTLISTED';
    public const STATUS_WAITLISTED  = 'WAITLISTED';
    public const STATUS_ADMITTED    = 'ADMITTED';
    public const STATUS_REJECTED    = 'REJECTED';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'application_id'   => 'integer',
            'class_applied_id' => 'integer',
        ]);

        $this->dates = array_merge($this->dates, ['submitted_at', 'decided_at']);

        parent::__construct($data);
    }
}
