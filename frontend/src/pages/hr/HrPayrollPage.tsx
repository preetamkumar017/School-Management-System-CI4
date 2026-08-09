import { useState, useEffect } from "react";
import EmployeesPage from "./EmployeesPage";
import OrgPage from "./OrgPage";
import PayrollRunsPage from "./PayrollRunsPage";
import LeaveRequestsPage from "./LeaveRequestsPage";
import HolidaysPage from "./HolidaysPage";
import LeaveTypesPage from "./LeaveTypesPage";
import DailyAttendancePage from "../attendance/DailyAttendancePage";
import AttendanceSettingsPage from "../attendance/AttendanceSettingsPage";
import TeacherWorkloadDashboard from "./workload/TeacherWorkloadDashboard";
import AppraisalDashboard from "./appraisal/AppraisalDashboard";
import CommunicationDashboard from "./communication/CommunicationDashboard";
import { api } from "../../lib/api";

type TabKey = "employees" | "org" | "payroll" | "leave_management" | "time_attendance" | "workload" | "appraisal" | "communication";
type LeaveSubTabKey = "requests" | "holidays" | "leavetypes";
type TimeSubTabKey = "daily" | "rules";

export default function HrPayrollPage() {
  const [tab, setTab] = useState<TabKey>("employees");
  const [leaveSubTab, setLeaveSubTab] = useState<LeaveSubTabKey>("requests");
  const [timeSubTab, setTimeSubTab] = useState<TimeSubTabKey>("daily");
  const [pendingCount, setPendingCount] = useState(0);

  const fetchPendingCount = () => {
    api.get<{ data: any[] }>("/hr-payroll/leave-requests")
      .then((res) => {
        const pending = res.data.data.filter((r) => r.status === "Pending");
        setPendingCount(pending.length);
      })
      .catch(() => {});
  };

  useEffect(() => {
    fetchPendingCount();
  }, [tab, leaveSubTab]);

  const TABS = [
    { key: "employees", label: "Employees" },
    { key: "org", label: "Departments/Designations" },
    { key: "payroll", label: "Payroll Runs" },
    { key: "time_attendance", label: "Time & Attendance" },
    { key: "workload", label: "Teacher Workload" },
    { key: "appraisal", label: "Performance & Appraisal" },
    { key: "leave_management", label: "Leave Management" },
    { key: "communication", label: "Staff Communication" },
  ] as const;

  return (
    <div>
      <div className="mb-6 flex flex-wrap gap-1 border-b border-slate-200 dark:border-slate-800">
        {TABS.map((t) => (
          <button
            key={t.key}
            type="button"
            onClick={() => setTab(t.key)}
            className={`border-b-2 px-4 py-2 text-sm font-medium transition flex items-center gap-2 ${
              tab === t.key
                ? "border-slate-900 text-slate-900 dark:border-slate-100 dark:text-slate-100"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
            }`}
          >
            <span>{t.label}</span>
            {t.key === "leave_management" && pendingCount > 0 && (
              <span className="rounded-full bg-amber-500 px-1.5 py-0.5 text-[10px] font-bold text-white dark:bg-amber-600">
                {pendingCount}
              </span>
            )}
          </button>
        ))}
      </div>

      {tab === "employees" && <EmployeesPage />}
      {tab === "org" && <OrgPage />}
      {tab === "payroll" && <PayrollRunsPage />}
      
      {tab === "leave_management" && (
        <div className="space-y-6">
          {/* Sub Navigation Tabs */}
          <div className="flex gap-2 rounded-xl bg-slate-100 p-1 dark:bg-slate-800/60 w-max">
            <button
              onClick={() => setLeaveSubTab("requests")}
              className={`rounded-lg px-4 py-1.5 text-xs font-semibold transition-all flex items-center gap-1.5 ${
                leaveSubTab === "requests"
                  ? "bg-white text-slate-900 shadow-sm dark:bg-slate-900 dark:text-slate-100"
                  : "text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200"
              }`}
            >
              <span>📩 Leave Requests</span>
              {pendingCount > 0 && (
                <span className="rounded-full bg-amber-500 px-1.5 py-0.5 text-[9px] font-bold text-white">
                  {pendingCount}
                </span>
              )}
            </button>
            <button
              onClick={() => setLeaveSubTab("holidays")}
              className={`rounded-lg px-4 py-1.5 text-xs font-semibold transition-all ${
                leaveSubTab === "holidays"
                  ? "bg-white text-slate-900 shadow-sm dark:bg-slate-900 dark:text-slate-100"
                  : "text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200"
              }`}
            >
              🗓️ Holidays
            </button>
            <button
              onClick={() => setLeaveSubTab("leavetypes")}
              className={`rounded-lg px-4 py-1.5 text-xs font-semibold transition-all ${
                leaveSubTab === "leavetypes"
                  ? "bg-white text-slate-900 shadow-sm dark:bg-slate-900 dark:text-slate-100"
                  : "text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200"
              }`}
            >
              ⚙️ Leave Types
            </button>
          </div>

          <div className="mt-4">
            {leaveSubTab === "requests" && <LeaveRequestsPage />}
            {leaveSubTab === "holidays" && <HolidaysPage />}
            {leaveSubTab === "leavetypes" && <LeaveTypesPage />}
          </div>
        </div>
      )}

      {tab === "time_attendance" && (
        <div className="space-y-6">
          {/* Sub Navigation Tabs */}
          <div className="flex gap-2 rounded-xl bg-slate-100 p-1 dark:bg-slate-800/60 w-max">
            <button
              onClick={() => setTimeSubTab("daily")}
              className={`rounded-lg px-4 py-1.5 text-xs font-semibold transition-all ${
                timeSubTab === "daily"
                  ? "bg-white text-slate-900 shadow-sm dark:bg-slate-900 dark:text-slate-100"
                  : "text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200"
              }`}
            >
              📊 Daily HR View
            </button>
            <button
              onClick={() => setTimeSubTab("rules")}
              className={`rounded-lg px-4 py-1.5 text-xs font-semibold transition-all ${
                timeSubTab === "rules"
                  ? "bg-white text-slate-900 shadow-sm dark:bg-slate-900 dark:text-slate-100"
                  : "text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200"
              }`}
            >
              ⚙️ Attendance Rules
            </button>
          </div>

          <div className="mt-4">
            {timeSubTab === "daily" && <DailyAttendancePage />}
            {timeSubTab === "rules" && <AttendanceSettingsPage />}
          </div>
        </div>
      )}

      {tab === "workload" && <TeacherWorkloadDashboard />}
      {tab === "appraisal" && <AppraisalDashboard />}
      {tab === "communication" && <CommunicationDashboard />}
    </div>
  );
}
