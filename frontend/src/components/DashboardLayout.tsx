import type { ReactNode } from "react";
import { NavLink, useNavigate } from "react-router-dom";
import { useAuth } from "../lib/auth";

const NAV_SECTIONS: { label: string; to: string }[] = [
  { label: "Dashboard", to: "/" },
  { label: "My HR", to: "/my-hr" },
  { label: "Academic", to: "/academic" },
  { label: "Admission", to: "/admission" },
  { label: "Students (SIS)", to: "/students" },
  { label: "Examination", to: "/examination" },
  { label: "Timetable", to: "/timetable" },
  { label: "Attendance", to: "/attendance" },
  { label: "Fees", to: "/fees" },
  { label: "HR & Payroll", to: "/hr-payroll" },
  { label: "Library", to: "/library" },
  { label: "Transport", to: "/transport" },
  { label: "Communication", to: "/communication" },
  { label: "Reports", to: "/reports" },
  { label: "Administration", to: "/administration" },
];

export default function DashboardLayout({ children }: { children: ReactNode }) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  async function handleLogout() {
    await logout();
    navigate("/login", { replace: true });
  }

  return (
    <div className="flex min-h-screen bg-slate-50 dark:bg-slate-900">
      <aside className="w-60 shrink-0 border-r border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
        <div className="border-b border-slate-200 px-4 py-4 dark:border-slate-800">
          <span className="text-sm font-semibold text-slate-900 dark:text-slate-100">School ERP</span>
        </div>
        <nav className="flex flex-col gap-0.5 p-2">
          {NAV_SECTIONS.map((section) => (
            <NavLink
              key={section.to}
              to={section.to}
              end={section.to === "/"}
              className={({ isActive }) =>
                `rounded-md px-3 py-2 text-sm transition ${
                  isActive
                    ? "bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900"
                    : "text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-900"
                }`
              }
            >
              {section.label}
            </NavLink>
          ))}
        </nav>
      </aside>

      <div className="flex flex-1 flex-col">
        <header className="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-3 dark:border-slate-800 dark:bg-slate-950">
          <div />
          <div className="flex items-center gap-4 text-sm">
            {user && <span className="text-slate-500 dark:text-slate-400">User #{user.userId}</span>}
            <button
              type="button"
              onClick={handleLogout}
              className="rounded-md border border-slate-300 px-3 py-1.5 text-slate-700 transition hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-900"
            >
              Sign out
            </button>
          </div>
        </header>

        <main className="flex-1 p-6">{children}</main>
      </div>
    </div>
  );
}
