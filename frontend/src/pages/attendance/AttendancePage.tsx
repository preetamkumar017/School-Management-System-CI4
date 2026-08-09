import { useState } from "react";
import MarkAttendancePage from "./MarkAttendancePage";
import PercentagePage from "./PercentagePage";
import DailyAttendancePage from "./DailyAttendancePage";
import AttendanceSettingsPage from "./AttendanceSettingsPage";

const TABS = [
  { key: "mark", label: "Mark Attendance" },
  { key: "percentage", label: "Percentage Lookup" },
  { key: "daily", label: "Daily HR View" },
  { key: "settings", label: "Attendance Rules" },
] as const;

type TabKey = (typeof TABS)[number]["key"];

export default function AttendancePage() {
  const [tab, setTab] = useState<TabKey>("mark");

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

      {tab === "mark" && <MarkAttendancePage />}
      {tab === "percentage" && <PercentagePage />}
      {tab === "daily" && <DailyAttendancePage />}
      {tab === "settings" && <AttendanceSettingsPage />}
    </div>
  );
}
