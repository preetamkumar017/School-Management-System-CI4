<?php

declare(strict_types=1);

namespace App\Modules\Fees\Models;

use App\Core\BaseModel;
use App\Modules\Fees\Entities\Invoice;

/**
 * docs/design/fees/Phase-2-Model-DTO-Design.md
 */
class InvoiceModel extends BaseModel
{
    protected $table          = 'invoices';
    protected $primaryKey     = 'invoice_id';
    protected $returnType     = Invoice::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'invoice_no',
        'student_id',
        'academic_session_id',
        'total_amount',
        'due_date',
        'status',
        'is_locked',
        'late_fee_applied',
        'created_by',
        'updated_by',
    ];

    public function existsByInvoiceNo(string $value): bool
    {
        return $this->where('invoice_no', $value)->countAllResults() > 0;
    }

    /**
     * @return list<Invoice>
     */
    public function findByStudentId(int $studentId): array
    {
        return $this->where('student_id', $studentId)->findAll();
    }

    /**
     * Input to InvoiceService::flagOverdueAsDefaulter (BR-FEE-008).
     *
     * @return list<Invoice>
     */
    public function findOverdueUnpaid(string $asOfDate): array
    {
        return $this->where('due_date <', $asOfDate)
            ->whereNotIn('status', [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED])
            ->findAll();
    }
}
