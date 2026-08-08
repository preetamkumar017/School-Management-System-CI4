import { useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";

interface PayrollRun {
  payroll_run_id: number;
  employee_id: number;
  pay_period: string;
  gross_pay: number;
  deductions_json: Record<string, number>;
  net_pay: number;
  status: "Draft" | "Approved" | "Processed";
}

interface FormState {
  pay_period: string;
  gross_pay: string;
  pf: string;
}

const EMPTY_FORM: FormState = { pay_period: "", gross_pay: "", pf: "" };

export default function PayrollRunsPage() {
  const [employeeIdInput, setEmployeeIdInput] = useState("");
  const [employeeId, setEmployeeId] = useState<number | null>(null);
  const [runs, setRuns] = useState<PayrollRun[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const [isCreating, setIsCreating] = useState(false);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function reload(forEmployeeId: number) {
    setIsLoading(true);
    setError(null);
    api
      .get<{ data: PayrollRun[] }>("/hr-payroll/payroll-runs", { params: { employee_id: forEmployeeId } })
      .then((response) => setRuns(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  function handleSearch(event: FormEvent) {
    event.preventDefault();
    const id = Number(employeeIdInput);
    if (id > 0) {
      setEmployeeId(id);
      reload(id);
    }
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    if (employeeId === null) return;
    setFormError(null);
    setIsSubmitting(true);
    try {
      await api.post("/hr-payroll/payroll-runs", {
        employee_id: employeeId,
        pay_period: form.pay_period,
        gross_pay: Number(form.gross_pay),
        deductions_json: form.pf ? { PF: Number(form.pf) } : {},
      });
      setIsCreating(false);
      reload(employeeId);
    } catch (err) {
      setFormError(apiErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleAction(run: PayrollRun, action: "approve" | "process") {
    setMessage(null);
    try {
      await api.post(`/hr-payroll/payroll-runs/${run.payroll_run_id}/${action}`);
      if (employeeId) reload(employeeId);
    } catch (err) {
      setMessage(apiErrorMessage(err));
    }
  }

  async function handleGeneratePayslip(run: PayrollRun) {
    setMessage(null);
    try {
      const response = await api.post<{ data: { document_id: number } }>(
        `/hr-payroll/payroll-runs/${run.payroll_run_id}/generate-payslip`,
      );
      const documentId = response.data.data.document_id;
      const pdfResponse = await api.get(`/administration/documents/${documentId}/download`, { responseType: "blob" });
      const blobUrl = URL.createObjectURL(pdfResponse.data as Blob);
      window.open(blobUrl, "_blank");
    } catch (err) {
      setMessage(apiErrorMessage(err));
    }
  }

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Payroll Runs</h2>
        {employeeId !== null && (
          <button
            type="button"
            onClick={() => {
              setForm(EMPTY_FORM);
              setFormError(null);
              setIsCreating(true);
            }}
            className={primaryButtonClass}
          >
            New Payroll Run
          </button>
        )}
      </div>

      <form onSubmit={handleSearch} className="mb-4 flex gap-2">
        <input
          type="number"
          min={1}
          placeholder="Employee ID"
          value={employeeIdInput}
          onChange={(e) => setEmployeeIdInput(e.target.value)}
          className={`${inputClass} w-40`}
        />
        <button type="submit" className={secondaryButtonClass}>
          Search
        </button>
      </form>

      {message && <p className="mb-3 text-sm text-red-600 dark:text-red-400">{message}</p>}
      {employeeId === null && <p className="text-sm text-slate-400">Enter an Employee ID to see payroll runs.</p>}
      {isLoading && <p className="text-sm text-slate-500 dark:text-slate-400">Loading…</p>}
      {error && (
        <p role="alert" className="text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}

      {employeeId !== null && !isLoading && !error && (
        <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
              <tr>
                <th className="px-4 py-2 font-medium">Pay period</th>
                <th className="px-4 py-2 font-medium">Gross</th>
                <th className="px-4 py-2 font-medium">Net</th>
                <th className="px-4 py-2 font-medium">Status</th>
                <th className="px-4 py-2" />
              </tr>
            </thead>
            <tbody>
              {runs.map((run) => (
                <tr key={run.payroll_run_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{run.pay_period}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">₹{run.gross_pay}</td>
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">₹{run.net_pay}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{run.status}</td>
                  <td className="px-4 py-2 text-right">
                    {run.status === "Draft" && (
                      <button
                        type="button"
                        onClick={() => handleAction(run, "approve")}
                        className="mr-2 text-xs text-slate-600 hover:underline dark:text-slate-400"
                      >
                        Approve
                      </button>
                    )}
                    {run.status === "Approved" && (
                      <button
                        type="button"
                        onClick={() => handleAction(run, "process")}
                        className="mr-2 text-xs text-slate-600 hover:underline dark:text-slate-400"
                      >
                        Process
                      </button>
                    )}
                    {run.status === "Processed" && (
                      <button
                        type="button"
                        onClick={() => handleGeneratePayslip(run)}
                        className="text-xs text-slate-600 hover:underline dark:text-slate-400"
                      >
                        Payslip PDF
                      </button>
                    )}
                  </td>
                </tr>
              ))}
              {runs.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-slate-400">
                    No payroll runs for this employee.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {isCreating && employeeId !== null && (
        <Modal title="New Payroll Run" onClose={() => setIsCreating(false)}>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className={labelClass}>Pay period (e.g. 2026-08)</label>
              <input
                required
                value={form.pay_period}
                onChange={(e) => setForm({ ...form, pay_period: e.target.value })}
                className={inputClass}
              />
            </div>
            <div>
              <label className={labelClass}>Gross pay</label>
              <input
                required
                type="number"
                min={0}
                value={form.gross_pay}
                onChange={(e) => setForm({ ...form, gross_pay: e.target.value })}
                className={inputClass}
              />
            </div>
            <div>
              <label className={labelClass}>PF deduction (optional)</label>
              <input type="number" min={0} value={form.pf} onChange={(e) => setForm({ ...form, pf: e.target.value })} className={inputClass} />
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
                {isSubmitting ? "Saving…" : "Save"}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
