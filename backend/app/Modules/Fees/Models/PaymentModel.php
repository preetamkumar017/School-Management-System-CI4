<?php

declare(strict_types=1);

namespace App\Modules\Fees\Models;

use App\Core\BaseModel;
use App\Modules\Fees\Entities\Payment;

/**
 * docs/design/fees/Phase-2-Model-DTO-Design.md
 */
class PaymentModel extends BaseModel
{
    protected $table          = 'payments';
    protected $primaryKey     = 'payment_id';
    protected $returnType     = Payment::class;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'invoice_id',
        'amount_paid',
        'payment_mode',
        'gateway_transaction_ref',
        'paid_at',
        'status',
        'created_by',
        'updated_by',
    ];

    public function existsByGatewayTransactionRef(string $value): bool
    {
        return $this->where('gateway_transaction_ref', $value)->countAllResults() > 0;
    }

    /**
     * @return list<Payment>
     */
    public function findByInvoiceId(int $invoiceId): array
    {
        return $this->where('invoice_id', $invoiceId)->findAll();
    }

    public function sumSuccessfulByInvoiceId(int $invoiceId): float
    {
        $row = $this->asArray()
            ->selectSum('amount_paid')
            ->where('invoice_id', $invoiceId)
            ->where('status', Payment::STATUS_SUCCESS)
            ->first();

        return (float) ($row['amount_paid'] ?? 0.0);
    }

    /**
     * docs/ADR/ADR-022-reports-dashboard.md — Fee collection summary
     * (report area 1): total collected for a session (sum of SUCCESS
     * payments against that session's invoices).
     */
    public function sumSuccessfulByInvoiceSession(int $academicSessionId): float
    {
        $row = $this->db->query(
            'SELECT SUM(p.amount_paid) AS collected FROM payments p '
                . 'JOIN invoices i ON i.invoice_id = p.invoice_id AND i.deleted_at IS NULL '
                . 'WHERE p.status = ? AND p.deleted_at IS NULL AND i.academic_session_id = ?',
            [Payment::STATUS_SUCCESS, $academicSessionId],
        )->getRowArray();

        return (float) ($row['collected'] ?? 0.0);
    }

    /**
     * Same total, broken down by the class each invoice's student
     * currently belongs to (same Student.section_id -> Section.class_id
     * resolution as InvoiceModel::sumOutstandingByClassForSession).
     *
     * @return array<int, float> class_id => collected amount
     */
    public function sumSuccessfulByClassForSession(int $academicSessionId): array
    {
        $rows = $this->db->query(
            'SELECT sec.class_id AS class_id, SUM(p.amount_paid) AS collected '
                . 'FROM payments p '
                . 'JOIN invoices i ON i.invoice_id = p.invoice_id AND i.deleted_at IS NULL '
                . 'JOIN students st ON st.student_id = i.student_id AND st.deleted_at IS NULL '
                . 'JOIN sections sec ON sec.section_id = st.section_id AND sec.deleted_at IS NULL '
                . 'WHERE p.status = ? AND p.deleted_at IS NULL AND i.academic_session_id = ? '
                . 'GROUP BY sec.class_id',
            [Payment::STATUS_SUCCESS, $academicSessionId],
        )->getResultArray();

        $result = [];

        foreach ($rows as $row) {
            $result[(int) $row['class_id']] = (float) $row['collected'];
        }

        return $result;
    }
}
