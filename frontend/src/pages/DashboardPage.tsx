import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { api, apiErrorMessage } from "../lib/api";
import { useAuth } from "../lib/auth";

interface SummaryData {
  generated_at: string;
  total_users: number;
  total_classes: number;
  total_academic_sessions: number;
  total_departments: number;
  total_designations: number;
  total_employees: number;
  total_books: number;
  books_available: number;
  total_vehicles: number;
  total_routes: number;
  total_fee_heads: number;
}

const TILES: { key: keyof SummaryData; label: string }[] = [
  { key: "total_users", label: "Users" },
  { key: "total_classes", label: "Classes" },
  { key: "total_academic_sessions", label: "Academic Sessions" },
  { key: "total_employees", label: "Employees" },
  { key: "total_departments", label: "Departments" },
  { key: "total_books", label: "Books" },
  { key: "books_available", label: "Books Available" },
  { key: "total_vehicles", label: "Vehicles" },
  { key: "total_routes", label: "Routes" },
  { key: "total_fee_heads", label: "Fee Heads" },
];

export default function DashboardPage() {
  const { user } = useAuth();
  const [summary, setSummary] = useState<SummaryData | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [pendingLeaves, setPendingLeaves] = useState(0);
  const [dismissedAlerts, setDismissedAlerts] = useState<string[]>([]);
  const [notices, setNotices] = useState<any[]>([]);

  const canViewReports = user?.permissionSet.includes("reports.manage");

  useEffect(() => {
    let cancelled = false;

    if (canViewReports) {
      api
        .get<{ data: SummaryData }>("/reports/summary")
        .then((response) => {
          if (!cancelled) setSummary(response.data.data);
        })
        .catch((err) => {
          if (!cancelled) setError(apiErrorMessage(err));
        })
        .finally(() => {
          if (!cancelled) setIsLoading(false);
        });
    } else {
      setIsLoading(false);
    }

    const fetchPendingLeaves = () => {
      if (user?.permissionSet.includes("hr_payroll.manage")) {
        api
          .get<{ data: any[] }>("/hr-payroll/leave-requests")
          .then((res) => {
            if (!cancelled) {
              setPendingLeaves(res.data.data.filter((r) => r.status === "Pending").length);
            }
          })
          .catch(() => {});
      }
    };

    const fetchNotices = () => {
      api
        .get<{ data: any[] }>("/hr-payroll/communications/feed")
        .then((res) => {
          if (!cancelled) setNotices(res.data.data);
        })
        .catch(() => {});
    };

    fetchPendingLeaves();
    fetchNotices();
    const interval = setInterval(fetchPendingLeaves, 10000);

    return () => {
      cancelled = true;
      clearInterval(interval);
    };
  }, [canViewReports, user]);

  // Dismiss action helper
  const dismissAlert = (key: string) => {
    setDismissedAlerts((prev) => [...prev, key]);
  };

  // Helper to render modular pending Action Center
  const renderActionCenter = () => {
    const alertsList: { key: string; icon: string; title: string; desc: string; link: string }[] = [];

    if (pendingLeaves > 0 && !dismissedAlerts.includes("leaves")) {
      alertsList.push({
        key: "leaves",
        icon: "📩",
        title: "Pending Leave Requests",
        desc: `${pendingLeaves} staff leave requests awaiting your decision.`,
        link: "/hr-payroll",
      });
    }

    if (alertsList.length === 0) return null;

    return (
      <div className="mb-6 space-y-3">
        <div className="flex items-center justify-between">
          <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
            ⚡ Action Center
          </h3>
          <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
            {alertsList.length} Action{alertsList.length > 1 ? "s" : ""} Required
          </span>
        </div>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {alertsList.map((alert) => (
            <div
              key={alert.key}
              className="relative flex flex-col justify-between rounded-xl border border-amber-200/80 bg-amber-50/50 p-4 transition-all hover:border-amber-300 dark:border-amber-900/40 dark:bg-amber-950/10"
            >
              {/* Dismiss Button */}
              <button
                onClick={() => dismissAlert(alert.key)}
                className="absolute right-3 top-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-xs font-bold"
                title="Dismiss"
              >
                ✕
              </button>
              <div className="pr-6">
                <div className="flex items-center gap-2 mb-1">
                  <span className="text-base">{alert.icon}</span>
                  <h4 className="text-sm font-bold text-slate-900 dark:text-slate-100">
                    {alert.title}
                  </h4>
                </div>
                <p className="text-xs text-slate-500 dark:text-slate-400">
                  {alert.desc}
                </p>
              </div>
              <div className="mt-4">
                <Link
                  to="/hr-payroll?tab=leave_management&sub=requests"
                  className="inline-block rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-200"
                >
                  Review Action →
                </Link>
              </div>
            </div>
          ))}
        </div>
      </div>
    );
  };

  if (!canViewReports) {
    return (
      <div>
        <div className="mb-6 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-950">
          <h1 className="text-xl font-bold text-slate-900 dark:text-slate-100">Welcome to Staff Portal 👋</h1>
          <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Access your personal employee profile, leave applications, attendance, and payslips below.
          </p>
        </div>

        {renderActionCenter()}

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <Link
            to="/my-hr"
            className="group rounded-xl border border-slate-200 bg-white p-5 transition hover:border-slate-400 dark:border-slate-800 dark:bg-slate-950 dark:hover:border-slate-600"
          >
            <h3 className="font-semibold text-slate-900 group-hover:text-blue-600 dark:text-slate-100 dark:group-hover:text-blue-400">
              My HR & Leaves
            </h3>
            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
              View profile details, check leave balances, submit leave requests, and download payslips.
            </p>
          </Link>
        </div>
        
        {/* School Notice Board for Regular Users */}
        {notices.length > 0 && (
          <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 mt-6">
            <h2 className="mb-4 text-lg font-bold text-slate-900 dark:text-white">📌 School Notice Board</h2>
            <div className="space-y-4">
              {notices.slice(0, 3).map((notice) => (
                <div key={notice.communication_id} className={`rounded-lg p-4 border ${notice.is_pinned ? 'border-indigo-200 bg-indigo-50/50 dark:border-indigo-900/50 dark:bg-indigo-900/10' : 'border-slate-100 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/30'}`}>
                  <div className="flex justify-between items-start mb-2">
                    <h3 className="font-semibold text-slate-900 dark:text-white">{notice.title}</h3>
                    <span className="text-xs bg-slate-200 text-slate-700 px-2 py-0.5 rounded-full dark:bg-slate-700 dark:text-slate-300">
                      {notice.type}
                    </span>
                  </div>
                  <p className="text-sm text-slate-600 dark:text-slate-400 whitespace-pre-wrap">{notice.message}</p>
                  <div className="mt-2 text-xs text-slate-500">
                    Published: {notice.publish_date}
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    );
  }

  return (
    <div>
      <h1 className="mb-6 text-lg font-semibold text-slate-900 dark:text-slate-100">Institutional Overview Dashboard</h1>

      {renderActionCenter()}

      {isLoading && <p className="text-sm text-slate-500 dark:text-slate-400">Loading…</p>}

      {error && (
        <p role="alert" className="text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}

      {summary && (
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
          {TILES.map((tile) => (
            <div
              key={tile.key}
              className="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950"
            >
              <p className="text-xs font-medium text-slate-500 dark:text-slate-400">{tile.label}</p>
              <p className="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">{summary[tile.key]}</p>
            </div>
          ))}
        </div>
      )}

      {/* School Notice Board for Admin Users */}
      {notices.length > 0 && (
        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 mt-6">
          <h2 className="mb-4 text-lg font-bold text-slate-900 dark:text-white">📌 School Notice Board</h2>
          <div className="space-y-4">
            {notices.slice(0, 3).map((notice) => (
              <div key={notice.communication_id} className={`rounded-lg p-4 border ${notice.is_pinned ? 'border-indigo-200 bg-indigo-50/50 dark:border-indigo-900/50 dark:bg-indigo-900/10' : 'border-slate-100 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/30'}`}>
                <div className="flex justify-between items-start mb-2">
                  <h3 className="font-semibold text-slate-900 dark:text-white">{notice.title}</h3>
                  <span className="text-xs bg-slate-200 text-slate-700 px-2 py-0.5 rounded-full dark:bg-slate-700 dark:text-slate-300">
                    {notice.type}
                  </span>
                </div>
                <p className="text-sm text-slate-600 dark:text-slate-400 whitespace-pre-wrap">{notice.message}</p>
                <div className="mt-2 text-xs text-slate-500">
                  Published: {notice.publish_date}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
