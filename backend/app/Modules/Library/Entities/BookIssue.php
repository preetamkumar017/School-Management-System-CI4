<?php

declare(strict_types=1);

namespace App\Modules\Library\Entities;

use App\Core\BaseEntity;

/**
 * docs/design/library/Phase-1-Domain-Model.md — ENT-LIB-002. Includes
 * the decided additive status/replacement_charge_amount/fine_settled
 * columns (ADR-009 §1, §4, §6).
 *
 * @property int|null $book_issue_id
 * @property int      $book_id
 * @property string   $borrower_type
 * @property int      $borrower_ref_id
 * @property string   $issue_date
 * @property string   $due_date
 * @property string|null $return_date
 * @property float    $fine_amount
 * @property string   $status
 * @property float    $replacement_charge_amount
 * @property bool     $fine_settled
 */
class BookIssue extends BaseEntity
{
    public const BORROWER_STUDENT = 'Student';
    public const BORROWER_EMPLOYEE = 'Employee';

    public const STATUS_ISSUED   = 'Issued';
    public const STATUS_RETURNED = 'Returned';
    public const STATUS_LOST     = 'Lost';

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->casts = array_merge($this->casts, [
            'book_issue_id'             => 'integer',
            'book_id'                   => 'integer',
            'borrower_ref_id'           => 'integer',
            'fine_amount'               => 'float',
            'replacement_charge_amount' => 'float',
            'fine_settled'              => 'boolean',
        ]);

        parent::__construct($data);
    }
}
