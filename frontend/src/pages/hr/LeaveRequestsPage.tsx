import { useEffect, useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";
import { useEmployees } from "./EmployeesPage";

interface LeaveRequest {
  leave_request_id: number;
  employee_id: number;
  leave_type: "CL" | "SL" | "EL" | "ML" | "LWP" | "DL";
  start_date: string;
  end_date: string;
  reason?: string;
  duty_leave_reference?: string;
  status: "Pending" | "Approved" | "Rejected";
  approver_id: number | null;
}

interface FormState {
  employee_id: string;
  leave_type: LeaveRequest["leave_type"];
  start_date: string;
  end_date: string;
  reason: string;
  duty_leave_reference: string;
}

const EMPTY_FORM: FormState = {
  employee_id: "",
  leave_type: "CL",
  start_date: "",
  end_date: "",
  reason: "",
  duty_leave_reference: "",
};

interface LeaveBalances {
  employee_id: number;
  year: number;
  balances: Record<
    "CL" | "SL" | "EL",
    { allocation: number; consumed: number; remaining: number }
  >;
}

const LEAVE_TYPE_LABELS: Record<LeaveRequest["leave_type"], string> = {
  CL: "Casual Leave",
  SL: "Sick Leave",
  EL: "Earned Leave",
  ML: "Maternity Leave",
  LWP: "Loss of Pay (Unpaid)",
  DL: "Duty Leave (Official Duty)",
};

export default function LeaveRequestsPage() {
  const { employees, isLoading: isLoadingEmployees } = useEmployees();
  const [selectedEmployeeId, setSelectedEmployeeId] = useState<string>("");
  const [requests, setRequests] = useState<LeaveRequest[]>([]);
  const [balances, setBalances] = useState<LeaveBalances | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const [isCreating, setIsCreating] = useState(false);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const currentEmployee = employees.find((e) => String(e.employee_id) === selectedEmployeeId);

  function reload(forEmployeeId: number) {
    setIsLoading(true);
    setError(null);
    Promise.all([
      api.get<{ data: LeaveRequest[] }>("/hr-payroll/leave-requests", { params: { employee_id: forEmployeeId } }),
      api.get<{ data: LeaveBalances }>("/hr-payroll/leave-requests/balance", { params: { employee_id: forEmployeeId } }),
    ])
      .then(([requestsResponse, balanceResponse]) => {
        setRequests(requestsResponse.data.data);
        setBalances(balanceResponse.data.data);
      })
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  useEffect(() => {
    if (employees.length > 0 && !selectedEmployeeId) {
      const firstId = String(employees[0].employee_id);
      setSelectedEmployeeId(firstId);
      reload(employees[0].employee_id);
    }
  }, [employees]);

  function handleSelectEmployee(idStr: string) {
    setSelectedEmployeeId(idStr);
    if (idStr) reload(Number(idStr));
  }

  function openCreate() {
    setForm({ ...EMPTY_FORM, employee_id: selectedEmployeeId });
    setFormError(null);
    setIsCreating(true);
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    const empId = Number(form.employee_id);
    if (!empId) return;

    setFormError(null);
    setIsSubmitting(true);
    try {
      await api.post("/hr-payroll/leave-requests", {
        employee_id: empId,
        leave_type: form.leave_type,
        start_date: form.start_date,
        end_date: form.end_date,
        reason: form.reason || null,
        duty_leave_reference: form.duty_leave_reference || null,
      });
      setIsCreating(false);
      reload(empId);
    } catch (err) {
      setFormError(apiErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDecide(request: LeaveRequest, decision: "Approved" | "Rejected") {
    setMessage(null);
    try {
      await api.post(`/hr-payroll/leave-requests/${request.leave_request_id}/decide`, { decision });
      if (selectedEmployeeId) reload(Number(selectedEmployeeId));
    } catch (err) {
      setMessage(apiErrorMessage(err));
    }
  }

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <div>
          <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Leave Requests & Allocations</h2>
          <p className="text-xs text-slate-500">Manage employee leave applications and balances</p>
        </div>
        <button type="button" onClick={openCreate} className={primaryButtonClass}>
          New Leave Request
        </button>
      </div>

      {/* Employee Selector Dropdown */}
      <div className="mb-6 flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/50">
        <label className="text-sm font-semibold text-slate-700 dark:text-slate-300">Select Employee:</label>
        <select
          value={selectedEmployeeId}
          onChange={(e) => handleSelectEmployee(e.target.value)}
          className={`${inputClass} max-w-md font-medium text-slate-900 dark:text-slate-100`}
        >
          {employees.map((emp) => (
            <option key={emp.employee_id} value={emp.employee_id}>
              {emp.employee_code} — {emp.full_name} ({emp.staff_type || "Staff"})
            </option>
          ))}
        </select>
      </div>

      {message && <p className="mb-3 text-sm text-red-600 dark:text-red-400">{message}</p>}
      {isLoadingEmployees && <p className="text-sm text-slate-500">Loading staff directory…</p>}
      {isLoading && <p className="text-sm text-slate-500 dark:text-slate-400">Loading leave requests…</p>}
      {error && (
        <p role="alert" className="text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}

      {selectedEmployeeId && !isLoading && !error && (
        <>
          {balances && (
            <div className="mb-6">
              <h3 className="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                Annual Leave Balances ({balances.year}) — {currentEmployee?.full_name}
              </h3>
              <div className="grid grid-cols-3 gap-3">
                {(["CL", "SL", "EL"] as const).map((type) => {
                  const b = balances.balances[type];
                  return (
                    <div
                      key={type}
                      className="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-950"
                    >
                      <p className="text-xs font-medium text-slate-500 dark:text-slate-400">{LEAVE_TYPE_LABELS[type]}</p>
                      <p className="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        {b ? `${b.remaining} remaining` : "N/A"}
                      </p>
                      {b && (
                        <p className="text-xs text-slate-400">
                          {b.consumed} used of {b.allocation}
                        </p>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>
          )}

          <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
                <tr>
                  <th className="px-4 py-2 font-medium">Type</th>
                  <th className="px-4 py-2 font-medium">Dates</th>
                  <th className="px-4 py-2 font-medium">Reason & Reference</th>
                  <th className="px-4 py-2 font-medium">Status</th>
                  <th className="px-4 py-2 text-right">Decision</th>
                </tr>
              </thead>
              <tbody>
                {requests.map((r) => (
                  <tr key={r.leave_request_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                    <td className="px-4 py-2 font-semibold text-slate-900 dark:text-slate-100">
                      {LEAVE_TYPE_LABELS[r.leave_type] || r.leave_type}
                    </td>
                    <td className="px-4 py-2 text-slate-500 dark:text-slate-400">
                      {r.start_date} to {r.end_date}
                    </td>
                    <td className="px-4 py-2 text-slate-500 dark:text-slate-400">
                      <div>{r.reason || "N/A"}</div>
                      {r.duty_leave_reference && (
                        <div className="text-xs font-medium text-blue-600 dark:text-blue-400">
                          Ref: {r.duty_leave_reference}
                        </div>
                      )}
                    </td>
                    <td className="px-4 py-2">
                      <span
                        className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                          r.status === "Approved"
                            ? "bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-400"
                            : r.status === "Rejected"
                            ? "bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-400"
                            : "bg-slate-100 text-slate-600 dark:bg-slate-900 dark:text-slate-400"
                        }`}
                      >
                        {r.status}
                      </span>
                    </td>
                    <td className="px-4 py-2 text-right">
                      {r.status === "Pending" && (
                        <div className="flex justify-end gap-1">
                          <button
                            type="button"
                            onClick={() => handleDecide(r, "Approved")}
                            className="rounded border border-green-300 px-2 py-1 text-xs text-green-700 hover:bg-green-50 dark:border-green-700 dark:text-green-300"
                          >
                            Approve
                          </button>
                          <button
                            type="button"
                            onClick={() => handleDecide(r, "Rejected")}
                            className="rounded border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50 dark:border-red-700 dark:text-red-300"
                          >
                            Reject
                          </button>
                        </div>
                      )}
                    </td>
                  </tr>
                ))}
                {requests.length === 0 && (
                  <tr>
                    <td colSpan={5} className="px-4 py-6 text-center text-slate-400">
                      No leave requests for this employee.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </>
      )}

      {isCreating && (
        <Modal title="New Leave Request" onClose={() => setIsCreating(false)} maxWidth="2xl">
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
                    {emp.employee_code} — {emp.full_name}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className={labelClass}>Leave Type</label>
              <select
                required
                value={form.leave_type}
                onChange={(e) => setForm({ ...form, leave_type: e.target.value as FormState["leave_type"] })}
                className={inputClass}
              >
                <option value="CL">Casual Leave (CL)</option>
                <option value="SL">Sick Leave (SL)</option>
                <option value="EL">Earned Leave (EL)</option>
                <option value="ML">Maternity Leave (ML - 90/180 Days)</option>
                <option value="LWP">Loss of Pay (LWP - Unpaid)</option>
                <option value="DL">Duty Leave (DL - Official Duty)</option>
              </select>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className={labelClass}>Start Date</label>
                <input
                  required
                  type="date"
                  value={form.start_date}
                  onChange={(e) => setForm({ ...form, start_date: e.target.value })}
                  className={inputClass}
                />
              </div>
              <div>
                <label className={labelClass}>End Date</label>
                <input
                  required
                  type="date"
                  value={form.end_date}
                  onChange={(e) => setForm({ ...form, end_date: e.target.value })}
                  className={inputClass}
                />
              </div>
            </div>

            <div>
              <label className={labelClass}>Reason</label>
              <textarea
                rows={2}
                placeholder="Reason for leave request…"
                value={form.reason}
                onChange={(e) => setForm({ ...form, reason: e.target.value })}
                className={inputClass}
              />
            </div>

            {form.leave_type === "DL" && (
              <div>
                <label className={labelClass}>Duty Leave Reference / Order No.</label>
                <input
                  placeholder="e.g. CBSE/EVAL/2026/ORDER-992"
                  value={form.duty_leave_reference}
                  onChange={(e) => setForm({ ...form, duty_leave_reference: e.target.value })}
                  className={inputClass}
                />
              </div>
            )}

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
                {isSubmitting ? "Submitting…" : "Submit Request"}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
