import { useState } from "react";
import EmployeesPage from "./EmployeesPage";
import OrgPage from "./OrgPage";
import PayrollRunsPage from "./PayrollRunsPage";
import LeaveRequestsPage from "./LeaveRequestsPage";

const TABS = [
  { key: "employees", label: "Employees" },
  { key: "org", label: "Departments/Designations" },
  { key: "payroll", label: "Payroll Runs" },
  { key: "leave", label: "Leave Requests" },
] as const;

type TabKey = (typeof TABS)[number]["key"];

export default function HrPayrollPage() {
  const [tab, setTab] = useState<TabKey>("employees");

  return (
    <div>
      <div className="mb-6 flex gap-1 border-b border-slate-200 dark:border-slate-800">
        {TABS.map((t) => (
          <button
            key={t.key}
            type="button"
            onClick={() => setTab(t.key)}
            className={`border-b-2 px-4 py-2 text-sm font-medium transition ${
              tab === t.key
                ? "border-slate-900 text-slate-900 dark:border-slate-100 dark:text-slate-100"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      {tab === "employees" && <EmployeesPage />}
      {tab === "org" && <OrgPage />}
      {tab === "payroll" && <PayrollRunsPage />}
      {tab === "leave" && <LeaveRequestsPage />}
    </div>
  );
}
