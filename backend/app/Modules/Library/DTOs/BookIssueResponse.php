<?php

declare(strict_types=1);

namespace App\Modules\Library\DTOs;

use App\Modules\Library\Entities\BookIssue;

/**
 * docs/design/library/Phase-2-Model-DTO-Design.md
 */
final class BookIssueResponse
{
    public readonly int $bookIssueId;
    public readonly int $bookId;
    public readonly string $borrowerType;
    public readonly int $borrowerRefId;
    public readonly string $issueDate;
    public readonly string $dueDate;
    public readonly ?string $returnDate;
    public readonly float $fineAmount;
    public readonly string $status;
    public readonly float $replacementChargeAmount;
    public readonly bool $fineSettled;

    public function __construct(BookIssue $bookIssue)
    {
        $this->bookIssueId             = $bookIssue->book_issue_id;
        $this->bookId                  = $bookIssue->book_id;
        $this->borrowerType            = $bookIssue->borrower_type;
        $this->borrowerRefId           = $bookIssue->borrower_ref_id;
        $this->issueDate               = $bookIssue->issue_date;
        $this->dueDate                 = $bookIssue->due_date;
        $this->returnDate              = $bookIssue->return_date;
        $this->fineAmount              = $bookIssue->fine_amount;
        $this->status                  = $bookIssue->status;
        $this->replacementChargeAmount = $bookIssue->replacement_charge_amount;
        $this->fineSettled             = $bookIssue->fine_settled;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'book_issue_id'             => $this->bookIssueId,
            'book_id'                   => $this->bookId,
            'borrower_type'             => $this->borrowerType,
            'borrower_ref_id'           => $this->borrowerRefId,
            'issue_date'                => $this->issueDate,
            'due_date'                  => $this->dueDate,
            'return_date'               => $this->returnDate,
            'fine_amount'               => $this->fineAmount,
            'status'                    => $this->status,
            'replacement_charge_amount' => $this->replacementChargeAmount,
            'fine_settled'              => $this->fineSettled,
        ];
    }
}
