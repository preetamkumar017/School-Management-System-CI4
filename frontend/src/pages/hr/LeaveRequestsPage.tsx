import { useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";

interface LeaveRequest {
  leave_request_id: number;
  employee_id: number;
  leave_type: "CL" | "SL" | "EL";
  start_date: string;
  end_date: string;
  status: "Pending" | "Approved" | "Rejected";
  approver_id: number | null;
}

interface FormState {
  leave_type: LeaveRequest["leave_type"];
  start_date: string;
  end_date: string;
}

const EMPTY_FORM: FormState = { leave_type: "CL", start_date: "", end_date: "" };

interface LeaveBalances {
  employee_id: number;
  year: number;
  balances: Record<
    "CL" | "SL" | "EL",
    { allocation: number; consumed: number; remaining: number }
  >;
}

const LEAVE_TYPE_LABELS: Record<"CL" | "SL" | "EL", string> = {
  CL: "Casual Leave",
  SL: "Sick Leave",
  EL: "Earned Leave",
};

export default function LeaveRequestsPage() {
  const [employeeIdInput, setEmployeeIdInput] = useState("");
  const [employeeId, setEmployeeId] = useState<number | null>(null);
  const [requests, setRequests] = useState<LeaveRequest[]>([]);
  const [balances, setBalances] = useState<LeaveBalances | null>(null);
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
      await api.post("/hr-payroll/leave-requests", {
        employee_id: employeeId,
        leave_type: form.leave_type,
        start_date: form.start_date,
        end_date: form.end_date,
      });
      setIsCreating(false);
      reload(employeeId);
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
      if (employeeId) reload(employeeId);
    } catch (err) {
      setMessage(apiErrorMessage(err));
    }
  }

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Leave Requests</h2>
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
            New Leave Request
          </button>
        )}
      </div>

      <p className="mb-4 text-sm text-slate-400">
        BR-HR-004: CL 12/SL 10/EL 15 annual allocation. Approving past the balance needs the
        <code className="mx-1 rounded bg-slate-100 px-1 dark:bg-slate-900">hr_payroll.leave.override</code>
        permission (ADR-015) — this account may not have it.
      </p>

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
      {employeeId === null && <p className="text-sm text-slate-400">Enter an Employee ID to see leave requests.</p>}
      {isLoading && <p className="text-sm text-slate-500 dark:text-slate-400">Loading…</p>}
      {error && (
        <p role="alert" className="text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}

      {employeeId !== null && balances && !isLoading && !error && (
        <div className="mb-4 grid grid-cols-3 gap-3">
          {(["CL", "SL", "EL"] as const).map((type) => {
            const b = balances.balances[type];
            const low = b.remaining <= 2;
            return (
              <div
                key={type}
                className="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950"
              >
                <p className="text-xs font-medium text-slate-500 dark:text-slate-400">
                  {LEAVE_TYPE_LABELS[type]} ({balances.year})
                </p>
                <p
                  className={`mt-1 text-2xl font-semibold ${
                    low ? "text-amber-600 dark:text-amber-400" : "text-slate-900 dark:text-slate-100"
                  }`}
                >
                  {b.remaining} <span className="text-sm font-normal text-slate-400">/ {b.allocation} left</span>
                </p>
                <p className="text-xs text-slate-400">{b.consumed} used</p>
              </div>
            );
          })}
        </div>
      )}

      {employeeId !== null && !isLoading && !error && (
        <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
              <tr>
                <th className="px-4 py-2 font-medium">Type</th>
                <th className="px-4 py-2 font-medium">From</th>
                <th className="px-4 py-2 font-medium">To</th>
                <th className="px-4 py-2 font-medium">Status</th>
                <th className="px-4 py-2" />
              </tr>
            </thead>
            <tbody>
              {requests.map((r) => (
                <tr key={r.leave_request_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{r.leave_type}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{r.start_date}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{r.end_date}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{r.status}</td>
                  <td className="px-4 py-2 text-right">
                    {r.status === "Pending" && (
                      <>
                        <button
                          type="button"
                          onClick={() => handleDecide(r, "Approved")}
                          className="mr-2 text-xs text-green-700 hover:underline dark:text-green-400"
                        >
                          Approve
                        </button>
                        <button
                          type="button"
                          onClick={() => handleDecide(r, "Rejected")}
                          className="text-xs text-red-600 hover:underline dark:text-red-400"
                        >
                          Reject
                        </button>
                      </>
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
      )}

      {isCreating && employeeId !== null && (
        <Modal title="New Leave Request" onClose={() => setIsCreating(false)}>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className={labelClass}>Leave type</label>
              <select
                value={form.leave_type}
                onChange={(e) => setForm({ ...form, leave_type: e.target.value as FormState["leave_type"] })}
                className={inputClass}
              >
                <option value="CL">Casual Leave</option>
                <option value="SL">Sick Leave</option>
                <option value="EL">Earned Leave</option>
              </select>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className={labelClass}>Start date</label>
                <input
                  required
                  type="date"
                  value={form.start_date}
                  onChange={(e) => setForm({ ...form, start_date: e.target.value })}
                  className={inputClass}
                />
              </div>
              <div>
                <label className={labelClass}>End date</label>
                <input
                  required
                  type="date"
                  value={form.end_date}
                  onChange={(e) => setForm({ ...form, end_date: e.target.value })}
                  className={inputClass}
                />
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
                {isSubmitting ? "Saving…" : "Save"}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
