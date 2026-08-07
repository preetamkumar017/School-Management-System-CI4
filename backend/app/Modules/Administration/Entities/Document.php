<?php

declare(strict_types=1);

namespace App\Modules\Administration\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/administration/Phase-8-Document-Design.md — ENT-SYS-006.
 *
 * @property int|null $document_id
 * @property string   $owner_type
 * @property int      $owner_ref_id
 * @property string   $document_type
 * @property string   $file_path
 * @property int      $uploaded_by
 * @property string   $uploaded_at
 */
class Document extends BaseEntity
{
    public const OWNER_APPLICATION = 'Application';
    public const OWNER_STUDENT     = 'Student';
    public const OWNER_INVOICE     = 'Invoice';
    public const OWNER_REPORT_CARD = 'ReportCard';
    public const OWNER_PAYROLL_RUN = 'PayrollRun';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'document_id'   => 'integer',
            'owner_ref_id'  => 'integer',
            'uploaded_by'   => 'integer',
        ]);

        parent::__construct($data);
    }
}
