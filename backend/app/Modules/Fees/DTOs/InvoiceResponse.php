<?php

declare(strict_types=1);

namespace App\Modules\Fees\DTOs;

use App\Modules\Fees\Entities\Invoice;

/**
 * docs/design/fees/Phase-2-Model-DTO-Design.md
 */
final class InvoiceResponse
{
    public readonly int $invoiceId;
    public readonly string $invoiceNo;
    public readonly int $studentId;
    public readonly int $academicSessionId;
    public readonly float $totalAmount;
    public readonly string $dueDate;
    public readonly string $status;
    public readonly bool $isLocked;

    public function __construct(Invoice $invoice)
    {
        $this->invoiceId         = $invoice->invoice_id;
        $this->invoiceNo         = $invoice->invoice_no;
        $this->studentId         = $invoice->student_id;
        $this->academicSessionId = $invoice->academic_session_id;
        $this->totalAmount       = $invoice->total_amount;
        $this->dueDate           = (string) $invoice->due_date;
        $this->status            = $invoice->status;
        $this->isLocked          = $invoice->is_locked;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'invoice_id'          => $this->invoiceId,
            'invoice_no'          => $this->invoiceNo,
            'student_id'          => $this->studentId,
            'academic_session_id' => $this->academicSessionId,
            'total_amount'        => $this->totalAmount,
            'due_date'            => $this->dueDate,
            'status'              => $this->status,
            'is_locked'           => $this->isLocked,
        ];
    }
}
