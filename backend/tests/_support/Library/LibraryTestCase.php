<?php

declare(strict_types=1);

namespace Tests\Support\Library;

use App\Modules\Library\Models\BookIssueModel;
use App\Modules\Library\Models\BookModel;
use Tests\Support\HrPayroll\HrPayrollTestCase;

/**
 * @internal
 */
abstract class LibraryTestCase extends HrPayrollTestCase
{
    protected function createBookFixture(
        ?string $barcode = null,
        string $classification = 'Circulating',
        bool $isAvailable = true,
    ): int {
        return (new BookModel())->insert([
            'barcode'        => $barcode ?? ('LIB-' . random_int(100000, 999999)),
            'title'          => 'Book ' . uniqid('', true),
            'author'         => 'Author ' . uniqid('', true),
            'classification' => $classification,
            'is_available'   => $isAvailable,
        ], true);
    }

    protected function createBookIssueFixture(
        ?int $bookId = null,
        string $borrowerType = 'Student',
        ?int $borrowerRefId = null,
        string $status = 'Issued',
        string $dueDate = '2026-08-20',
        float $fineAmount = 0.0,
        bool $fineSettled = false,
    ): int {
        return (new BookIssueModel())->insert([
            'book_id'         => $bookId ?? $this->createBookFixture(),
            'borrower_type'   => $borrowerType,
            'borrower_ref_id' => $borrowerRefId ?? ($borrowerType === 'Student' ? $this->createStudentFixture() : $this->createEmployeeFixture()),
            'issue_date'      => '2026-08-01',
            'due_date'        => $dueDate,
            'status'          => $status,
            'fine_amount'     => $fineAmount,
            'fine_settled'    => $fineSettled,
        ], true);
    }
}
