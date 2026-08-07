<?php

declare(strict_types=1);

namespace App\Modules\Library\Models;

use App\Core\BaseModel;
use App\Modules\Library\Entities\BookIssue;

/**
 * docs/design/library/Phase-2-Model-DTO-Design.md
 */
class BookIssueModel extends BaseModel
{
    protected $table          = 'book_issues';
    protected $primaryKey     = 'book_issue_id';
    protected $returnType     = BookIssue::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'book_id',
        'borrower_type',
        'borrower_ref_id',
        'issue_date',
        'due_date',
        'return_date',
        'fine_amount',
        'status',
        'replacement_charge_amount',
        'fine_settled',
        'created_by',
        'updated_by',
    ];

    public function countIssuedByBorrower(string $borrowerType, int $borrowerRefId): int
    {
        return $this->where('borrower_type', $borrowerType)
            ->where('borrower_ref_id', $borrowerRefId)
            ->where('status', BookIssue::STATUS_ISSUED)
            ->countAllResults();
    }

    public function sumUnsettledFinesByBorrower(string $borrowerType, int $borrowerRefId): float
    {
        $rows = $this->where('borrower_type', $borrowerType)
            ->where('borrower_ref_id', $borrowerRefId)
            ->where('fine_settled', false)
            ->findAll();

        $total = 0.0;

        foreach ($rows as $row) {
            $total += $row->fine_amount + $row->replacement_charge_amount;
        }

        return $total;
    }

    /**
     * @return list<BookIssue>
     */
    public function findByBorrower(string $borrowerType, int $borrowerRefId): array
    {
        return $this->where('borrower_type', $borrowerType)
            ->where('borrower_ref_id', $borrowerRefId)
            ->findAll();
    }

    public function findActiveByBookId(int $bookId): ?BookIssue
    {
        return $this->where('book_id', $bookId)
            ->where('status', BookIssue::STATUS_ISSUED)
            ->first();
    }
}
