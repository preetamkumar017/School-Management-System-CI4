import { useEffect, useState, type FormEvent } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import { useCurrentEmployee } from "../../lib/currentEmployee";
import { useDepartments, useDesignations } from "./OrgPage";
import Modal from "../../components/ui/Modal";
import { inputClass, labelClass, primaryButtonClass, secondaryButtonClass } from "../../components/ui/form";

interface LeaveRequest {
  leave_request_id: number;
  leave_type: "CL" | "SL" | "EL";
  start_date: string;
  end_date: string;
  status: "Pending" | "Approved" | "Rejected";
}

interface LeaveBalances {
  year: number;
  balances: Record<"CL" | "SL" | "EL", { allocation: number; consumed: number; remaining: number }>;
}

interface PayrollRun {
  payroll_run_id: number;
  pay_period: string;
  gross_pay: number;
  net_pay: number;
  status: "Draft" | "Approved" | "Processed";
}

const LEAVE_TYPE_LABELS: Record<"CL" | "SL" | "EL", string> = {
  CL: "Casual Leave",
  SL: "Sick Leave",
  EL: "Earned Leave",
};

const LEAVE_STATUS_STYLES: Record<LeaveRequest["status"], string> = {
  Pending: "bg-slate-100 text-slate-600 dark:bg-slate-900 dark:text-slate-400",
  Approved: "bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-400",
  Rejected: "bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-400",
};

export default function MyHrPage() {
  const { employee, isLoading, error } = useCurrentEmployee();
  const { departments } = useDepartments();
  const { designations } = useDesignations();

  const [leaveRequests, setLeaveRequests] = useState<LeaveRequest[]>([]);
  const [balances, setBalances] = useState<LeaveBalances | null>(null);
  const [payrollRuns, setPayrollRuns] = useState<PayrollRun[]>([]);
  const [message, setMessage] = useState<string | null>(null);

  const [isApplying, setIsApplying] = useState(false);
  const [leaveType, setLeaveType] = useState<"CL" | "SL" | "EL">("CL");
  const [startDate, setStartDate] = useState("");
  const [endDate, setEndDate] = useState("");
  const [applyError, setApplyError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  function reload(employeeId: number) {
    Promise.all([
      api.get<{ data: LeaveRequest[] }>("/hr-payroll/leave-requests", { params: { employee_id: employeeId } }),
      api.get<{ data: LeaveBalances }>("/hr-payroll/leave-requests/balance", { params: { employee_id: employeeId } }),
      api.get<{ data: PayrollRun[] }>("/hr-payroll/payroll-runs", { params: { employee_id: employeeId } }),
    ])
      .then(([leaveResponse, balanceResponse, payrollResponse]) => {
        setLeaveRequests(leaveResponse.data.data);
        setBalances(balanceResponse.data.data);
        setPayrollRuns(payrollResponse.data.data);
      })
      .catch((err) => setMessage(apiErrorMessage(err)));
  }

  useEffect(() => {
    if (employee) reload(employee.employee_id);
  }, [employee]);

  async function handleApply(event: FormEvent) {
    event.preventDefault();
    if (!employee) return;
    setApplyError(null);
    setIsSubmitting(true);
    try {
      await api.post("/hr-payroll/leave-requests", {
        employee_id: employee.employee_id,
        leave_type: leaveType,
        start_date: startDate,
        end_date: endDate,
      });
      setIsApplying(false);
      setStartDate("");
      setEndDate("");
      reload(employee.employee_id);
    } catch (err) {
      setApplyError(apiErrorMessage(err));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleDownloadPayslip(run: PayrollRun) {
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

  if (isLoading) {
    return <p className="text-sm text-slate-500 dark:text-slate-400">Loading…</p>;
  }

  if (error || !employee) {
    return (
      <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300">
        {error ?? "No employee record found for this login."}
      </div>
    );
  }

  const gross = Object.values(employee.salary_structure_json).reduce((sum, v) => sum + Number(v), 0);
  const departmentName = departments.find((d) => d.department_id === employee.department_id)?.department_name ?? "—";
  const designationName = designations.find((d) => d.designation_id === employee.designation_id)?.designation_name ?? "—";

  return (
    <div className="space-y-8">
      <section className="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950">
        <div className="flex items-start justify-between">
          <div>
            <h1 className="text-lg font-semibold text-slate-900 dark:text-slate-100">{employee.full_name}</h1>
            <p className="text-sm text-slate-500 dark:text-slate-400">
              {designationName} · {departmentName} · {employee.employee_code}
            </p>
          </div>
          <span
            className={`rounded-full px-2 py-0.5 text-xs font-medium ${
              employee.status === "Active"
                ? "bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-400"
                : "bg-slate-100 text-slate-600 dark:bg-slate-900 dark:text-slate-400"
            }`}
          >
            {employee.status}
          </span>
        </div>
        <div className="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
          <div>
            <p className="text-xs text-slate-400">Joined</p>
            <p className="text-slate-900 dark:text-slate-100">{employee.joining_date}</p>
          </div>
          <div>
            <p className="text-xs text-slate-400">Monthly gross</p>
            <p className="text-slate-900 dark:text-slate-100">₹{gross.toLocaleString()}</p>
          </div>
        </div>
      </section>

      {message && <p className="text-sm text-red-600 dark:text-red-400">{message}</p>}

      <section>
        <div className="mb-3 flex items-center justify-between">
          <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">My Leave</h2>
          <button type="button" onClick={() => setIsApplying(true)} className={primaryButtonClass}>
            Apply for Leave
          </button>
        </div>

        {balances && (
          <div className="mb-4 grid grid-cols-3 gap-3">
            {(["CL", "SL", "EL"] as const).map((type) => {
              const b = balances.balances[type];
              return (
                <div key={type} className="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                  <p className="text-xs font-medium text-slate-500 dark:text-slate-400">{LEAVE_TYPE_LABELS[type]}</p>
                  <p className="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">
                    {b.remaining} <span className="text-sm font-normal text-slate-400">/ {b.allocation}</span>
                  </p>
                </div>
              );
            })}
          </div>
        )}

        <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
              <tr>
                <th className="px-4 py-2 font-medium">Type</th>
                <th className="px-4 py-2 font-medium">From</th>
                <th className="px-4 py-2 font-medium">To</th>
                <th className="px-4 py-2 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              {leaveRequests.map((r) => (
                <tr key={r.leave_request_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{r.leave_type}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{r.start_date}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{r.end_date}</td>
                  <td className="px-4 py-2">
                    <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${LEAVE_STATUS_STYLES[r.status]}`}>
                      {r.status}
                    </span>
                  </td>
                </tr>
              ))}
              {leaveRequests.length === 0 && (
                <tr>
                  <td colSpan={4} className="px-4 py-6 text-center text-slate-400">
                    No leave requests yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>

      <section>
        <h2 className="mb-3 text-base font-semibold text-slate-900 dark:text-slate-100">My Payslips</h2>
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
              {payrollRuns.map((run) => (
                <tr key={run.payroll_run_id} className="border-b border-slate-100 last:border-0 dark:border-slate-900">
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">{run.pay_period}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">₹{run.gross_pay}</td>
                  <td className="px-4 py-2 text-slate-900 dark:text-slate-100">₹{run.net_pay}</td>
                  <td className="px-4 py-2 text-slate-500 dark:text-slate-400">{run.status}</td>
                  <td className="px-4 py-2 text-right">
                    {run.status === "Processed" && (
                      <button
                        type="button"
                        onClick={() => handleDownloadPayslip(run)}
                        className="text-xs text-slate-600 hover:underline dark:text-slate-400"
                      >
                        Download
                      </button>
                    )}
                  </td>
                </tr>
              ))}
              {payrollRuns.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-slate-400">
                    No payslips yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </section>

      {isApplying && (
        <Modal title="Apply for Leave" onClose={() => setIsApplying(false)}>
          <form onSubmit={handleApply} className="space-y-4">
            <div>
              <label className={labelClass}>Leave type</label>
              <select value={leaveType} onChange={(e) => setLeaveType(e.target.value as "CL" | "SL" | "EL")} className={inputClass}>
                <option value="CL">Casual Leave</option>
                <option value="SL">Sick Leave</option>
                <option value="EL">Earned Leave</option>
              </select>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className={labelClass}>Start date</label>
                <input required type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} className={inputClass} />
              </div>
              <div>
                <label className={labelClass}>End date</label>
                <input required type="date" value={endDate} onChange={(e) => setEndDate(e.target.value)} className={inputClass} />
              </div>
            </div>

            {applyError && (
              <p role="alert" className="text-sm text-red-600 dark:text-red-400">
                {applyError}
              </p>
            )}

            <div className="flex justify-end gap-2 pt-2">
              <button type="button" onClick={() => setIsApplying(false)} className={secondaryButtonClass}>
                Cancel
              </button>
              <button type="submit" disabled={isSubmitting} className={primaryButtonClass}>
                {isSubmitting ? "Submitting…" : "Submit"}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
