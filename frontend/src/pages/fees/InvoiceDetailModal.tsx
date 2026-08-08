import { useEffect, useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";
import type { Invoice } from "./InvoicesPage";

interface LineItem {
  invoice_line_item_id: number;
  fee_head_id: number;
  base_amount: number;
  waiver_amount: number;
  taxable_amount: number;
  gst_rate: number | null;
  gst_amount: number;
  line_total: number;
}

interface Payment {
  payment_id: number;
  invoice_id: number;
  amount_paid: number;
  payment_mode: string;
  gateway_transaction_ref: string | null;
  paid_at: string;
  status: "SUCCESS" | "FAILED" | "REFUNDED" | "VOIDED";
}

export default function InvoiceDetailModal({
  invoice,
  onClose,
  onChanged,
}: {
  invoice: Invoice;
  onClose: () => void;
  onChanged: () => void;
}) {
  const [lineItems, setLineItems] = useState<LineItem[]>([]);
  const [payments, setPayments] = useState<Payment[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const [amountPaid, setAmountPaid] = useState("");
  const [paymentMode, setPaymentMode] = useState("CASH");
  const [isRecordingPayment, setIsRecordingPayment] = useState(false);

  function reload() {
    Promise.all([
      api.get<{ data: LineItem[] }>(`/fees/invoices/${invoice.invoice_id}/line-items`),
      api.get<{ data: Payment[] }>("/fees/payments", { params: { invoice_id: invoice.invoice_id } }),
    ])
      .then(([lineItemsResponse, paymentsResponse]) => {
        setLineItems(lineItemsResponse.data.data);
        setPayments(paymentsResponse.data.data);
      })
      .catch((err) => setError(apiErrorMessage(err)));
  }

  useEffect(reload, [invoice.invoice_id]);

  async function handleRecordPayment(event: FormEvent) {
    event.preventDefault();
    setMessage(null);
    setIsRecordingPayment(true);
    try {
      await api.post("/fees/payments", {
        invoice_id: invoice.invoice_id,
        amount_paid: Number(amountPaid),
        payment_mode: paymentMode,
      });
      setAmountPaid("");
      reload();
      onChanged();
    } catch (err) {
      setMessage(apiErrorMessage(err));
    } finally {
      setIsRecordingPayment(false);
    }
  }

  async function handlePaymentAction(payment: Payment, action: "void" | "refund") {
    const reason = prompt(`Reason for ${action}:`);
    if (!reason) return;
    try {
      await api.post(`/fees/payments/${payment.payment_id}/${action}`, { reason });
      reload();
      onChanged();
    } catch (err) {
      setMessage(apiErrorMessage(err));
    }
  }

  async function handleApplyLateFee() {
    try {
      await api.post(`/fees/invoices/${invoice.invoice_id}/apply-late-fee`);
      onChanged();
    } catch (err) {
      setMessage(apiErrorMessage(err));
    }
  }

  async function handleFlagDefaulter() {
    try {
      await api.post(`/fees/invoices/${invoice.invoice_id}/flag-defaulter`);
      onChanged();
    } catch (err) {
      setMessage(apiErrorMessage(err));
    }
  }

  async function handleGeneratePdf() {
    setMessage(null);
    try {
      const response = await api.post<{ data: { document_id: number } }>(`/fees/invoices/${invoice.invoice_id}/generate-pdf`);
      const documentId = response.data.data.document_id;
      const pdfResponse = await api.get(`/administration/documents/${documentId}/download`, { responseType: "blob" });
      const blobUrl = URL.createObjectURL(pdfResponse.data as Blob);
      window.open(blobUrl, "_blank");
      setMessage("Invoice PDF generated.");
    } catch (err) {
      setMessage(apiErrorMessage(err));
    }
  }

  return (
    <Modal title={`Invoice ${invoice.invoice_no}`} onClose={onClose}>
      <div className="max-h-[70vh] space-y-6 overflow-y-auto pr-1">
        <div>
          <p className="text-sm text-slate-500 dark:text-slate-400">
            Status: <span className="font-medium text-slate-900 dark:text-slate-100">{invoice.status}</span> · Total: ₹
            {invoice.total_amount} · Due {invoice.due_date} {invoice.is_locked && "· Locked"}
          </p>
        </div>

        {error && (
          <p role="alert" className="text-sm text-red-600 dark:text-red-400">
            {error}
          </p>
        )}

        <div>
          <p className={labelClass}>Line items (BR-FEE-007 GST itemization)</p>
          <table className="w-full text-left text-sm">
            <thead className="text-slate-500 dark:text-slate-400">
              <tr>
                <th className="py-1 font-medium">Fee head</th>
                <th className="py-1 font-medium">Base</th>
                <th className="py-1 font-medium">Waiver</th>
                <th className="py-1 font-medium">GST</th>
                <th className="py-1 font-medium">Line total</th>
              </tr>
            </thead>
            <tbody>
              {lineItems.map((li) => (
                <tr key={li.invoice_line_item_id} className="border-t border-slate-100 dark:border-slate-900">
                  <td className="py-1 text-slate-900 dark:text-slate-100">Fee head #{li.fee_head_id}</td>
                  <td className="py-1 text-slate-500 dark:text-slate-400">₹{li.base_amount}</td>
                  <td className="py-1 text-slate-500 dark:text-slate-400">₹{li.waiver_amount}</td>
                  <td className="py-1 text-slate-500 dark:text-slate-400">
                    {li.gst_rate ? `${li.gst_rate}% (₹${li.gst_amount})` : "—"}
                  </td>
                  <td className="py-1 text-slate-900 dark:text-slate-100">₹{li.line_total}</td>
                </tr>
              ))}
              {lineItems.length === 0 && (
                <tr>
                  <td colSpan={5} className="py-3 text-center text-slate-400">
                    No line items.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        <div>
          <p className={labelClass}>Payments</p>
          <table className="w-full text-left text-sm">
            <thead className="text-slate-500 dark:text-slate-400">
              <tr>
                <th className="py-1 font-medium">Amount</th>
                <th className="py-1 font-medium">Mode</th>
                <th className="py-1 font-medium">Status</th>
                <th className="py-1 font-medium">Paid at</th>
                <th className="py-1" />
              </tr>
            </thead>
            <tbody>
              {payments.map((p) => (
                <tr key={p.payment_id} className="border-t border-slate-100 dark:border-slate-900">
                  <td className="py-1 text-slate-900 dark:text-slate-100">₹{p.amount_paid}</td>
                  <td className="py-1 text-slate-500 dark:text-slate-400">{p.payment_mode}</td>
                  <td className="py-1 text-slate-500 dark:text-slate-400">{p.status}</td>
                  <td className="py-1 text-slate-500 dark:text-slate-400">{p.paid_at}</td>
                  <td className="py-1 text-right">
                    {p.status === "SUCCESS" && (
                      <>
                        <button
                          type="button"
                          onClick={() => handlePaymentAction(p, "void")}
                          className="mr-2 text-xs text-amber-700 hover:underline dark:text-amber-400"
                        >
                          Void
                        </button>
                        <button
                          type="button"
                          onClick={() => handlePaymentAction(p, "refund")}
                          className="text-xs text-red-600 hover:underline dark:text-red-400"
                        >
                          Refund
                        </button>
                      </>
                    )}
                  </td>
                </tr>
              ))}
              {payments.length === 0 && (
                <tr>
                  <td colSpan={5} className="py-3 text-center text-slate-400">
                    No payments yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        <form onSubmit={handleRecordPayment} className="flex items-end gap-2 border-t border-slate-200 pt-4 dark:border-slate-800">
          <div className="flex-1">
            <label className={labelClass}>Record payment</label>
            <input
              required
              type="number"
              step="0.01"
              min={0.01}
              value={amountPaid}
              onChange={(e) => setAmountPaid(e.target.value)}
              placeholder="Amount"
              className={inputClass}
            />
          </div>
          <select value={paymentMode} onChange={(e) => setPaymentMode(e.target.value)} className={inputClass}>
            <option value="CASH">Cash</option>
            <option value="ONLINE">Online</option>
            <option value="CHEQUE">Cheque</option>
            <option value="BANK_TRANSFER">Bank Transfer</option>
          </select>
          <button type="submit" disabled={isRecordingPayment} className={primaryButtonClass}>
            {isRecordingPayment ? "Recording…" : "Record"}
          </button>
        </form>

        {message && <p className="text-sm text-slate-500 dark:text-slate-400">{message}</p>}

        <div className="flex flex-wrap gap-2 border-t border-slate-200 pt-4 dark:border-slate-800">
          <button type="button" onClick={handleApplyLateFee} className={secondaryButtonClass}>
            Apply Late Fee
          </button>
          <button type="button" onClick={handleFlagDefaulter} className={secondaryButtonClass}>
            Flag Defaulter
          </button>
          <button type="button" onClick={handleGeneratePdf} className={secondaryButtonClass}>
            Generate PDF
          </button>
          <button type="button" onClick={onClose} className={secondaryButtonClass}>
            Close
          </button>
        </div>
      </div>
    </Modal>
  );
}
