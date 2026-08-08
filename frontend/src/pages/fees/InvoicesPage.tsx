import { useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import { useAcademicSessions } from "../../lib/academic";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";
import InvoiceDetailModal from "./InvoiceDetailModal";

export interface Invoice {
  invoice_id: number;
  invoice_no: string;
  student_id: number;
  academic_session_id: number;
  total_amount: number;
  due_date: string;
  status: "UNPAID" | "PARTIALLY_PAID" | "PAID" | "DEFAULTER" | "CANCELLED";
  is_locked: boolean;
}

const STATUS_STYLES: Record<Invoice["status"], string> = {
  UNPAID: "bg-slate-100 text-slate-600 dark:bg-slate-900 dark:text-slate-400",
  PARTIALLY_PAID: "bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400",
  PAID: "bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-400",
  DEFAULTER: "bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-400",
  CANCELLED: "bg-slate-100 text-slate-500 dark:bg-slate-900 dark:text-slate-500",
};

export default function InvoicesPage() {
  const { sessions } = useAcademicSessions();
  const [studentIdInput, setStudentIdInput] = useState("");
  const [studentId, setStudentId] = useState<number | null>(null);
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [selected, setSelected] = useState<Invoice | null>(null);

  const [isCreating, setIsCreating] = useState(false);
  const [sessionId, setSessionId] = useState("");
  const [dueDate, setDueDate] = useState("");
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function loadInvoices(id: number) {
    setIsLoading(true);
    setError(null);
    api
      .get<{ data: Invoice[] }>("/fees/invoices", { params: { student_id: id } })
      .then((response) => setInvoices(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  function handleSearch(event: FormEvent) {
    event.preventDefault();
    const id = Number(studentIdInput);
    if (id > 0) {
      setStudentId(id);
      loadInvoices(id);
    }
  }

  async function handleCreateInvoice(event: FormEvent) {
    event.preventDefault();
    if (studentId === null) return;
    setFormError(null);
    setIsSubmitting(true);
    try {
      await api.post("/fees/invoices", {
        student_id: studentId,
        academic_session_id: Number(sessionId),
        due_date: dueDate,
      });
      setIsCreating(false);
      loadInvoices(studentId);
    } catch (err) {
      setFormError(apiErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Invoices</h2>
        {studentId !== null && (
          <button
            type="button"
            onClick={() => {
              setSessionId("");
              setDueDate("");
              setFormError(null);
              setIsCreating(true);
            }}
            className={primaryButtonClass}
          >
            New Invoice
          </button>
        )}
      </div>

      <form onSubmit={handleSearch} className="mb-4 flex gap-2">
        <input
          type="number"
          min={1}
          placeholder="Student ID"
          value={studentIdInput}
          onChange={(e) => setStudentIdInput(e.target.value)}
          className={`${inputClass} w-40`}
        />
        <button type="submit" className={secondaryButtonClass}>
          Search
        </button>
      </form>

      {studentId === null && <p className="text-sm text-slate-400">Enter a Student ID to see their invoices.</p>}
      {isLoading && <p className="text-sm text-slate-500 dark:text-slate-400">Loading…</p>}
      {error && (
        <p role="alert" className="text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}

      {studentId !== null && !isLoading && !error && (
        <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
              <tr>
                <th className="px-4 py-2 font-medium">Invoice #</th>
                <th className="px-4 py-2 font-medium">Total</th>
                <th className="px-4 py-2 font-medium">Due date</th>
                <th className="px-4 py-2 font-medium">Status</th>
                <th className="px-4 py-2" />
              </tr>
            </thead>
            <tbody>
              {invoices.map((invoice) => (
                <tr key={invoice.invoice_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{invoice.invoice_no}</td>
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">₹{invoice.total_amount}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{invoice.due_date}</td>
                  <td className="px-4 py-2">
                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_STYLES[invoice.status]}`}>
                      {invoice.status}
                    </span>
                  </td>
                  <td className="px-4 py-2 text-right">
                    <button
                      type="button"
                      onClick={() => setSelected(invoice)}
                      className="text-slate-600 hover:underline dark:text-slate-400"
                    >
                      View
                    </button>
                  </td>
                </tr>
              ))}
              {invoices.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-slate-400">
                    No invoices for this student.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {isCreating && studentId !== null && (
        <Modal title="New Invoice" onClose={() => setIsCreating(false)}>
          <form onSubmit={handleCreateInvoice} className="space-y-4">
            <div>
              <label className={labelClass}>Academic session</label>
              <select required value={sessionId} onChange={(e) => setSessionId(e.target.value)} className={inputClass}>
                <option value="" disabled>
                  Select session
                </option>
                {sessions.map((s) => (
                  <option key={s.academic_session_id} value={s.academic_session_id}>
                    {s.session_name}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className={labelClass}>Due date</label>
              <input required type="date" value={dueDate} onChange={(e) => setDueDate(e.target.value)} className={inputClass} />
            </div>

            {formError && (
              <p role="alert" className="text-sm text-red-600 dark:text-red-400">
                {formError}
              </p>
            )}

            <div className="flex justify-end gap-2 pt-2">
              <button type="button" onClick={() => setIsCreating(false)} className={secondaryButtonClass}>
                Cancel
              </button>
              <button type="submit" disabled={isSubmitting} className={primaryButtonClass}>
                {isSubmitting ? "Generating…" : "Generate"}
              </button>
            </div>
          </form>
        </Modal>
      )}

      {selected && (
        <InvoiceDetailModal
          invoice={selected}
          onClose={() => setSelected(null)}
          onChanged={() => {
            if (studentId !== null) loadInvoices(studentId);
          }}
        />
      )}
    </div>
  );
}
