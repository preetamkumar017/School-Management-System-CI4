<?php

declare(strict_types=1);

namespace App\Modules\Examination\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/examination/Phase-1-Domain-Model.md — ENT-EXM-004.
 *
 * @property int|null $promotion_record_id
 * @property int      $student_id
 * @property int      $from_session_id
 * @property int      $to_session_id
 * @property int      $from_class_id
 * @property int      $to_class_id
 * @property bool     $academic_closure_confirmed
 * @property bool     $fee_closure_confirmed
 */
class PromotionRecord extends BaseEntity
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'promotion_record_id'        => 'integer',
            'student_id'                 => 'integer',
            'from_session_id'            => 'integer',
            'to_session_id'              => 'integer',
            'from_class_id'              => 'integer',
            'to_class_id'                => 'integer',
            'academic_closure_confirmed' => 'boolean',
            'fee_closure_confirmed'      => 'boolean',
        ]);

        parent::__construct($data);
    }
}
