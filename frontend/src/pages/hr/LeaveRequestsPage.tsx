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
  CL: "Casual Leave (CL)",
  SL: "Sick Leave (SL)",
  EL: "Earned Leave (EL)",
  ML: "Maternity Leave (ML)",
  LWP: "Loss of Pay (LWP)",
  DL: "Duty Leave (DL)",
};

export default function LeaveRequestsPage() {
  const { employees, isLoading: isLoadingEmployees } = useEmployees();
  const [selectedStatus, setSelectedStatus] = useState<"All" | "Pending" | "Approved" | "Rejected">("All");
  const [selectedEmployeeId, setSelectedEmployeeId] = useState<string>("All");
  const [requests, setRequests] = useState<LeaveRequest[]>([]);
  const [balances, setBalances] = useState<LeaveBalances | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  const [isCreating, setIsCreating] = useState(false);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Rejection modal state
  const [rejectingRequest, setRejectingRequest] = useState<LeaveRequest | null>(null);
  const [rejectionReason, setRejectionReason] = useState("");
  const [isRejecting, setIsRejecting] = useState(false);

  function reload() {
    setIsLoading(true);
    setError(null);
    const params: Record<string, string | number> = {};
    if (selectedEmployeeId && selectedEmployeeId !== "All") {
      params.employee_id = Number(selectedEmployeeId);
    } else if (selectedStatus && selectedStatus !== "All") {
      params.status = selectedStatus;
    }

    const fetchRequests = api.get<{ data: LeaveRequest[] }>("/hr-payroll/leave-requests", { params });

    const fetchBalance =
      selectedEmployeeId && selectedEmployeeId !== "All"
        ? api.get<{ data: LeaveBalances }>("/hr-payroll/leave-requests/balance", {
            params: { employee_id: Number(selectedEmployeeId) },
          })
        : Promise.resolve(null);

    Promise.all([fetchRequests, fetchBalance])
      .then(([requestsResponse, balanceResponse]) => {
        setRequests(requestsResponse.data.data);
        if (balanceResponse) {
          setBalances(balanceResponse.data.data);
        } else {
          setBalances(null);
        }
      })
      .catch((err) => setError(apiErrorMessage(err)))
      .finally(() => setIsLoading(false));
  }

  useEffect(() => {
    reload();
  }, [selectedStatus, selectedEmployeeId]);

  function getEmployeeDetails(empId: number) {
    return employees.find((e) => e.employee_id === empId);
  }

  function openCreate() {
    const defaultEmp = selectedEmployeeId !== "All" ? selectedEmployeeId : employees[0] ? String(employees[0].employee_id) : "";
    setForm({ ...EMPTY_FORM, employee_id: defaultEmp });
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
      reload();
    } catch (err) {
      setFormError(apiErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleApprove(request: LeaveRequest) {
    setMessage(null);
    try {
      await api.post(`/hr-payroll/leave-requests/${request.leave_request_id}/decide`, { decision: "Approved" });
      reload();
    } catch (err) {
      setMessage(apiErrorMessage(err));
    }
  }

  function openRejectModal(request: LeaveRequest) {
    setRejectingRequest(request);
    setRejectionReason("");
  }

  async function confirmRejection(event: FormEvent) {
    event.preventDefault();
    if (!rejectingRequest) return;

    setMessage(null);
    setIsRejecting(true);
    try {
      await api.post(`/hr-payroll/leave-requests/${rejectingRequest.leave_request_id}/decide`, {
        decision: "Rejected",
        override_reason: rejectionReason || null,
      });
      setRejectingRequest(null);
      reload();
    } catch (err) {
      setMessage(apiErrorMessage(err));
    } finally {
      setIsRejecting(false);
    }
  }

  const pendingCount = requests.filter((r) => r.status === "Pending").length;

  return (
    <div>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">
            School Leave Management & Approval Inbox
          </h2>
          <p className="text-xs text-slate-500">
            View all staff leave applications across the school and approve/reject with 1-click
          </p>
        </div>
        <button type="button" onClick={openCreate} className={primaryButtonClass}>
          + New Leave Request
        </button>
      </div>

      {/* Filter Bar: Status & Employee Dropdown */}
      <div className="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/50">
        <div>
          <label className="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1 block">
            Filter by Status:
          </label>
          <div className="flex flex-wrap gap-1">
            {(["All", "Pending", "Approved", "Rejected"] as const).map((s) => (
              <button
                key={s}
                type="button"
                onClick={() => {
                  setSelectedStatus(s);
                  setSelectedEmployeeId("All");
                }}
                className={`rounded-lg px-3 py-1.5 text-xs font-medium transition ${
                  selectedStatus === s
                    ? "bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900 shadow-xs"
                    : "border border-slate-300 bg-white text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300 dark:hover:bg-slate-900"
                }`}
              >
                {s} {s === "Pending" && pendingCount > 0 ? `(${pendingCount})` : ""}
              </button>
            ))}
          </div>
        </div>

        <div>
          <label className="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1 block">
            Filter by Specific Staff Member:
          </label>
          <select
            value={selectedEmployeeId}
            onChange={(e) => {
              setSelectedEmployeeId(e.target.value);
              setSelectedStatus("All");
            }}
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

      {message && <p className="mb-3 text-sm text-red-600 dark:text-red-400">{message}</p>}
      {isLoadingEmployees && <p className="text-sm text-slate-500">Loading staff directory…</p>}
      {isLoading && <p className="text-sm text-slate-500 dark:text-slate-400">Loading leave inbox…</p>}
      {error && (
        <p role="alert" className="text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}

      {/* Selected Employee Balance Banner */}
      {balances && selectedEmployeeId !== "All" && (
        <div className="mb-6">
          <h3 className="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
            Annual Leave Balances ({balances.year}) — {getEmployeeDetails(Number(selectedEmployeeId))?.full_name}
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

      {!isLoading && !error && (
        <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
              <tr>
                <th className="px-4 py-2 font-medium">Applicant Staff</th>
                <th className="px-4 py-2 font-medium">Leave Type</th>
                <th className="px-4 py-2 font-medium">Dates</th>
                <th className="px-4 py-2 font-medium">Reason & Reference</th>
                <th className="px-4 py-2 font-medium">Status</th>
                <th className="px-4 py-2 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {requests.map((r) => {
                const emp = getEmployeeDetails(r.employee_id);
                return (
                  <tr key={r.leave_request_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                    <td className="px-4 py-2 font-medium text-slate-900 dark:text-slate-100">
                      <div>{emp?.full_name || `Employee #${r.employee_id}`}</div>
                      <div className="text-xs text-slate-500">
                        {emp?.employee_code} • {emp?.staff_type || "Staff"}
                      </div>
                    </td>
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
                            onClick={() => handleApprove(r)}
                            className="rounded border border-green-300 px-2.5 py-1 text-xs font-medium text-green-700 hover:bg-green-50 dark:border-green-700 dark:text-green-300"
                          >
                            Approve
                          </button>
                          <button
                            type="button"
                            onClick={() => openRejectModal(r)}
                            className="rounded border border-red-300 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-50 dark:border-red-700 dark:text-red-300"
                          >
                            Reject
                          </button>
                        </div>
                      )}
                    </td>
                  </tr>
                );
              })}
              {requests.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-4 py-6 text-center text-slate-400">
                    No leave requests match the selected filters.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {/* New Leave Request Modal */}
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
                    {emp.employee_code} — {emp.full_name} ({emp.staff_type || "Staff"})
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

      {/* Reject Leave Request Modal */}
      {rejectingRequest && (
        <Modal title="Reject Leave Request" onClose={() => setRejectingRequest(null)} maxWidth="lg">
          <form onSubmit={confirmRejection} className="space-y-4">
            <p className="text-sm text-slate-600 dark:text-slate-400">
              Rejecting leave application for{" "}
              <strong className="text-slate-900 dark:text-slate-100">
                {getEmployeeDetails(rejectingRequest.employee_id)?.full_name || `Employee #${rejectingRequest.employee_id}`}
              </strong>{" "}
              ({rejectingRequest.start_date} to {rejectingRequest.end_date}).
            </p>

            <div>
              <label className={labelClass}>Rejection Reason / Remark (Optional)</label>
              <textarea
                rows={3}
                placeholder="e.g. CBSE Board Exam evaluation week — leave not permitted. (Leave blank to reject without reason)"
                value={rejectionReason}
                onChange={(e) => setRejectionReason(e.target.value)}
                className={inputClass}
              />
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <button type="button" onClick={() => setRejectingRequest(null)} className={secondaryButtonClass}>
                Cancel
              </button>
              <button type="submit" disabled={isRejecting} className="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50">
                {isRejecting ? "Rejecting…" : "Confirm Rejection"}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
