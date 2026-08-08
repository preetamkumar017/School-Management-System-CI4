import { useEffect, useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";
import { useEmployees } from "./EmployeesPage";

interface PayrollRun {
  payroll_run_id: number;
  employee_id: number;
  pay_period: string;
  lwp_days?: number;
  gross_pay: number;
  earnings_json?: Record<string, number>;
  deductions_json: Record<string, number>;
  net_pay: number;
  status: "Draft" | "Approved" | "Processed";
}

interface FormState {
  employee_id: string;
  pay_period: string;
  gross_pay: string;
  lwp_days: string;
  pf: string;
  esi: string;
  pt: string;
  tds: string;
}

const EMPTY_FORM: FormState = {
  employee_id: "",
  pay_period: "",
  gross_pay: "",
  lwp_days: "0",
  pf: "",
  esi: "",
  pt: "200",
  tds: "",
};

export default function PayrollRunsPage() {
  const { employees, isLoading: isLoadingEmployees } = useEmployees();
  const [selectedPeriod, setSelectedPeriod] = useState<string>("2026-08");
  const [selectedEmployeeId, setSelectedEmployeeId] = useState<string>("All");
  const [runs, setRuns] = useState<PayrollRun[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const [isCreating, setIsCreating] = useState(false);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function reload() {
    setIsLoading(true);
    setError(null);
    const params: Record<string, string | number> = {};
    if (selectedEmployeeId && selectedEmployeeId !== "All") {
      params.employee_id = Number(selectedEmployeeId);
    } else if (selectedPeriod && selectedPeriod !== "All") {
      params.pay_period = selectedPeriod;
    }

    api
      .get<{ data: PayrollRun[] }>("/hr-payroll/payroll-runs", { params })
      .then((response) => setRuns(response.data.data))
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  useEffect(() => {
    reload();
  }, [selectedPeriod, selectedEmployeeId]);

  function getEmployeeDetails(empId: number) {
    return employees.find((e) => e.employee_id === empId);
  }

  function openCreate() {
    const emp = selectedEmployeeId !== "All" ? getEmployeeDetails(Number(selectedEmployeeId)) : employees[0];
    setForm({
      ...EMPTY_FORM,
      employee_id: emp ? String(emp.employee_id) : "",
      pay_period: selectedPeriod !== "All" ? selectedPeriod : "2026-08",
      gross_pay: emp ? String(Object.values(emp.salary_structure_json).reduce((a, b) => a + Number(b), 0)) : "",
    });
    setFormError(null);
    setIsCreating(true);
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    const empId = Number(form.employee_id);
    if (!empId) return;

    setFormError(null);
    setIsSubmitting(true);

    const deductions: Record<string, number> = {};
    if (form.pf) deductions.PF = Number(form.pf);
    if (form.esi) deductions.ESI = Number(form.esi);
    if (form.pt) deductions.PT = Number(form.pt);
    if (form.tds) deductions.TDS = Number(form.tds);

    const targetEmp = getEmployeeDetails(empId);

    try {
      await api.post("/hr-payroll/payroll-runs", {
        employee_id: empId,
        pay_period: form.pay_period,
        gross_pay: Number(form.gross_pay),
        lwp_days: Number(form.lwp_days || 0),
        deductions_json: deductions,
        earnings_json: targetEmp ? targetEmp.salary_structure_json : undefined,
      });
      setIsCreating(false);
      reload();
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
      reload();
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

  // Export Bank Payout Statement CSV for Accounts Department
  function handleExportBankStatement() {
    if (runs.length === 0) return;

    const headers = [
      "Employee Code",
      "Employee Name",
      "Staff Type",
      "Bank Name",
      "Bank Account Number",
      "IFSC Code",
      "Pay Period",
      "LWP Days",
      "Gross Pay",
      "Total Deductions",
      "Net Payable Amount",
      "Status",
    ];

    const rows = runs.map((run) => {
      const emp = getEmployeeDetails(run.employee_id);
      const totalDeductions = Object.values(run.deductions_json || {}).reduce((a, b) => a + Number(b), 0);
      return [
        `"${emp?.employee_code || run.employee_id}"`,
        `"${emp?.full_name || 'Staff'}"`,
        `"${emp?.staff_type || 'Teaching'}"`,
        `"${emp?.bank_name || 'State Bank of India'}"`,
        `"${emp?.bank_account_number || 'N/A'}"`,
        `"${emp?.bank_ifsc_code || 'N/A'}"`,
        `"${run.pay_period}"`,
        run.lwp_days ?? 0,
        run.gross_pay,
        totalDeductions,
        run.net_pay,
        `"${run.status}"`,
      ].join(",");
    });

    const csvContent = [headers.join(","), ...rows].join("\n");
    const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.setAttribute("href", url);
    link.setAttribute("download", `Bank_Salary_Payout_Statement_${selectedPeriod}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }

  const totalMonthlyPayout = runs.reduce((sum, r) => sum + r.net_pay, 0);

  return (
    <div>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">
            Monthly Payroll Register & Bank Payouts
          </h2>
          <p className="text-xs text-slate-500">
            Process monthly payroll for 1000+ staff members and export Bank Payout Statement for Accounts
          </p>
        </div>
        <div className="flex gap-2">
          <button
            type="button"
            onClick={handleExportBankStatement}
            disabled={runs.length === 0}
            className="rounded-lg border border-emerald-600 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 dark:border-emerald-500 dark:bg-emerald-950 dark:text-emerald-300 dark:hover:bg-emerald-900 disabled:opacity-50"
          >
            📊 Export Bank Payout Statement (CSV)
          </button>
          <button type="button" onClick={openCreate} className={primaryButtonClass}>
            + New Payroll Run
          </button>
        </div>
      </div>

      {/* Filter Bar: Pay Period & Staff Selector */}
      <div className="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/50">
        <div>
          <label className="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1 block">
            Filter by Month / Pay Period:
          </label>
          <select
            value={selectedPeriod}
            onChange={(e) => {
              setSelectedPeriod(e.target.value);
              setSelectedEmployeeId("All");
            }}
            className={`${inputClass} font-medium`}
          >
            <option value="2026-08">August 2026 (2026-08)</option>
            <option value="2026-07">July 2026 (2026-07)</option>
            <option value="All">All Months (Entire History)</option>
          </select>
        </div>

        <div>
          <label className="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1 block">
            Filter by Staff Member:
          </label>
          <select
            value={selectedEmployeeId}
            onChange={(e) => setSelectedEmployeeId(e.target.value)}
            className={`${inputClass} font-medium`}
          >
            <option value="All">All Staff Members ({employees.length} Employees)</option>
            {employees.map((emp) => (
              <option key={emp.employee_id} value={emp.employee_id}>
                {emp.employee_code} — {emp.full_name} ({emp.staff_type || "Staff"})
              </option>
            ))}
          </select>
        </div>
      </div>

      {/* Summary Stat Banner for Accounts */}
      <div className="mb-4 flex items-center justify-between rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
        <div>
          <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Net Bank Payout:</span>
          <span className="ml-3 text-xl font-bold text-slate-900 dark:text-slate-100">
            ₹{totalMonthlyPayout.toLocaleString("en-IN")}
          </span>
        </div>
        <div className="text-xs text-slate-500">
          Showing <span className="font-semibold text-slate-900 dark:text-slate-100">{runs.length}</span> payroll records
        </div>
      </div>

      {message && <p className="mb-3 text-sm text-red-600 dark:text-red-400">{message}</p>}
      {isLoadingEmployees && <p className="text-sm text-slate-500">Loading staff directory…</p>}
      {isLoading && <p className="text-sm text-slate-500 dark:text-slate-400">Loading payroll register…</p>}
      {error && (
        <p role="alert" className="text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}

      {!isLoading && !error && (
        <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
              <tr>
                <th className="px-4 py-2 font-medium">Employee</th>
                <th className="px-4 py-2 font-medium">Bank Account & IFSC</th>
                <th className="px-4 py-2 font-medium">Period</th>
                <th className="px-4 py-2 font-medium">Gross</th>
                <th className="px-4 py-2 font-medium">Net Payable</th>
                <th className="px-4 py-2 font-medium">Status</th>
                <th className="px-4 py-2 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {runs.map((run) => {
                const emp = getEmployeeDetails(run.employee_id);
                return (
                  <tr key={run.payroll_run_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                    <td className="px-4 py-2 font-medium text-slate-900 dark:text-slate-100">
                      <div>{emp?.full_name || `Employee #${run.employee_id}`}</div>
                      <div className="text-xs text-slate-500">
                        {emp?.employee_code} • {emp?.staff_type || "Staff"}
                      </div>
                    </td>
                    <td className="px-4 py-2 text-slate-500 dark:text-slate-400">
                      <div>{emp?.bank_name || "State Bank of India"}</div>
                      <div className="text-xs font-mono text-slate-400">
                        Acc: {emp?.bank_account_number || "N/A"} • IFSC: {emp?.bank_ifsc_code || "N/A"}
                      </div>
                    </td>
                    <td className="px-4 py-2 font-semibold text-slate-900 dark:text-slate-100">{run.pay_period}</td>
                    <td className="px-4 py-2 text-slate-500 dark:text-slate-400">₹{run.gross_pay.toLocaleString()}</td>
                    <td className="px-4 py-2 font-bold text-slate-900 dark:text-slate-100">₹{run.net_pay.toLocaleString()}</td>
                    <td className="px-4 py-2">
                      <span
                        className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                          run.status === "Processed"
                            ? "bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-400"
                            : run.status === "Approved"
                            ? "bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-400"
                            : "bg-slate-100 text-slate-600 dark:bg-slate-900 dark:text-slate-400"
                        }`}
                      >
                        {run.status}
                      </span>
                    </td>
                    <td className="px-4 py-2 text-right">
                      {run.status === "Draft" && (
                        <button
                          type="button"
                          onClick={() => handleAction(run, "approve")}
                          className="mr-2 rounded border border-blue-300 px-2 py-1 text-xs text-blue-700 hover:bg-blue-50 dark:border-blue-700 dark:text-blue-300"
                        >
                          Approve
                        </button>
                      )}
                      {run.status === "Approved" && (
                        <button
                          type="button"
                          onClick={() => handleAction(run, "process")}
                          className="mr-2 rounded border border-green-300 px-2 py-1 text-xs text-green-700 hover:bg-green-50 dark:border-green-700 dark:text-green-300"
                        >
                          Process Payroll
                        </button>
                      )}
                      {run.status === "Processed" && (
                        <button
                          type="button"
                          onClick={() => handleGeneratePayslip(run)}
                          className="rounded border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300"
                        >
                          📄 Payslip PDF
                        </button>
                      )}
                    </td>
                  </tr>
                );
              })}
              {runs.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-4 py-6 text-center text-slate-400">
                    No payroll runs found for the selected month/staff filter.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {isCreating && (
        <Modal title="Create Payroll Run" onClose={() => setIsCreating(false)} maxWidth="2xl">
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className={labelClass}>Employee</label>
              <select
                required
                value={form.employee_id}
                onChange={(e) => setForm({ ...form, employee_id: e.target.value })}
                className={inputClass}
              >
                {employees.map((emp) => (
                  <option key={emp.employee_id} value={emp.employee_id}>
                    {emp.employee_code} — {emp.full_name} ({emp.staff_type || "Staff"})
                  </option>
                ))}
              </select>
            </div>

            <div className="grid grid-cols-3 gap-3">
              <div>
                <label className={labelClass}>Pay Period (YYYY-MM)</label>
                <input
                  required
                  placeholder="2026-08"
                  value={form.pay_period}
                  onChange={(e) => setForm({ ...form, pay_period: e.target.value })}
                  className={inputClass}
                />
              </div>
              <div>
                <label className={labelClass}>Gross Pay (Monthly)</label>
                <input
                  required
                  type="number"
                  placeholder="60000"
                  value={form.gross_pay}
                  onChange={(e) => setForm({ ...form, gross_pay: e.target.value })}
                  className={inputClass}
                />
              </div>
              <div>
                <label className={labelClass}>LWP Days (Unpaid)</label>
                <input
                  type="number"
                  min={0}
                  placeholder="0"
                  value={form.lwp_days}
                  onChange={(e) => setForm({ ...form, lwp_days: e.target.value })}
                  className={inputClass}
                />
              </div>
            </div>

            <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-3 dark:border-slate-800 dark:bg-slate-900/50">
              <h4 className="text-xs font-semibold uppercase tracking-wider text-slate-500">Statutory Monthly Deductions</h4>
              <div className="grid grid-cols-4 gap-3">
                <div>
                  <label className={labelClass}>PF Deduction</label>
                  <input
                    type="number"
                    placeholder="e.g. 4800"
                    value={form.pf}
                    onChange={(e) => setForm({ ...form, pf: e.target.value })}
                    className={inputClass}
                  />
                </div>
                <div>
                  <label className={labelClass}>ESI Deduction</label>
                  <input
                    type="number"
                    placeholder="e.g. 450"
                    value={form.esi}
                    onChange={(e) => setForm({ ...form, esi: e.target.value })}
                    className={inputClass}
                  />
                </div>
                <div>
                  <label className={labelClass}>Prof Tax (PT)</label>
                  <input
                    type="number"
                    placeholder="200"
                    value={form.pt}
                    onChange={(e) => setForm({ ...form, pt: e.target.value })}
                    className={inputClass}
                  />
                </div>
                <div>
                  <label className={labelClass}>TDS Tax</label>
                  <input
                    type="number"
                    placeholder="e.g. 500"
                    value={form.tds}
                    onChange={(e) => setForm({ ...form, tds: e.target.value })}
                    className={inputClass}
                  />
                </div>
              </div>
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
                {isSubmitting ? "Creating…" : "Create Payroll Run"}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
