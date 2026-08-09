import type { ReactNode } from "react";
import { useEffect, useState, useRef } from "react";
import { NavLink, useNavigate } from "react-router-dom";
import { useAuth } from "../lib/auth";
import { api } from "../lib/api";

const NAV_SECTIONS: { label: string; to: string; permission?: string }[] = [
  { label: "Dashboard", to: "/" },
  { label: "My HR", to: "/my-hr" },
  { label: "Academic", to: "/academic", permission: "academic.manage" },
  { label: "Admission", to: "/admission", permission: "admission.manage" },
  { label: "Students (SIS)", to: "/students", permission: "sis.manage" },
  { label: "Examination", to: "/examination", permission: "examination.manage" },
  { label: "Timetable", to: "/timetable", permission: "timetable.manage" },
  { label: "Attendance", to: "/attendance", permission: "attendance.manage" },
  { label: "Fees", to: "/fees", permission: "fees.manage" },
  { label: "HR & Payroll", to: "/hr-payroll", permission: "hr_payroll.manage" },
  { label: "Library", to: "/library", permission: "library.manage" },
  { label: "Transport", to: "/transport", permission: "transport.manage" },
  { label: "Communication", to: "/communication", permission: "communication.manage" },
  { label: "Reports", to: "/reports", permission: "reports.manage" },
  { label: "Administration", to: "/administration", permission: "administration.manage" },
];

export default function DashboardLayout({ children }: { children: ReactNode }) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  async function handleLogout() {
    await logout();
    navigate("/login", { replace: true });
  }

  const visibleNavSections = NAV_SECTIONS.filter((section) => {
    if (!section.permission) return true;
    return user?.permissionSet.includes(section.permission);
  });

  const [pendingCount, setPendingCount] = useState(0);
  const [toast, setToast] = useState<{ title: string; desc: string } | null>(null);
  const [unreadComms, setUnreadComms] = useState<any[]>([]);
  const prevCountRef = useRef<number>(0);
  const isFirstLoad = useRef<boolean>(true);

  useEffect(() => {
    let cancelled = false;

    const pollUnread = () => {
      api.get<{ data: any[] }>("/hr-payroll/communications/unread")
        .then(res => {
          if (!cancelled) setUnreadComms(res.data.data);
        })
        .catch(() => {});
    };

    pollUnread();
    const interval = setInterval(pollUnread, 15000);

    return () => {
      cancelled = true;
      clearInterval(interval);
    };
  }, []);

  const handleMarkRead = async (id: number) => {
    try {
      await api.post(`/hr-payroll/communications/${id}/read`);
      setUnreadComms(prev => prev.filter(c => c.communication_id !== id));
    } catch (e) {
      console.error(e);
    }
  };

  const activeAlert = unreadComms.find(c => c.type === 'Alert');
  const activeToast = !activeAlert && unreadComms.length > 0 ? unreadComms[0] : null;

  const playBeep = () => {
    try {
      const ctx = new (window.AudioContext || (window as any).webkitAudioContext)();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = "sine";
      osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5 chime
      gain.gain.setValueAtTime(0.08, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start();
      osc.stop(ctx.currentTime + 0.3);
    } catch (_) {}
  };

  useEffect(() => {
    if (!user?.permissionSet.includes("hr_payroll.manage")) return;

    const checkPendingRequests = () => {
      api.get<{ data: any[] }>("/hr-payroll/leave-requests")
        .then((res) => {
          const pendingList = res.data.data.filter((r) => r.status === "Pending");
          const currentCount = pendingList.length;

          // If count has increased since last check
          if (!isFirstLoad.current && currentCount > prevCountRef.current) {
            playBeep();
            setToast({
              title: "📩 New Leave Request Received",
              desc: `A staff member has submitted a new leave request. Total pending: ${currentCount}`,
            });

            // Auto-clear toast after 10s
            setTimeout(() => setToast(null), 10000);
          }

          prevCountRef.current = currentCount;
          setPendingCount(currentCount);
          isFirstLoad.current = false;
        })
        .catch(() => {});
    };

    // Initial check
    checkPendingRequests();

    // Poll every 10 seconds for real-time notification alerts
    const interval = setInterval(checkPendingRequests, 10000);
    return () => clearInterval(interval);
  }, [user]);

  return (
    <div className="flex min-h-screen bg-slate-50 dark:bg-slate-900">
      <aside className="w-60 shrink-0 border-r border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
        <div className="border-b border-slate-200 px-4 py-4 dark:border-slate-800">
          <span className="text-sm font-semibold text-slate-900 dark:text-slate-100">School ERP</span>
        </div>
        <nav className="flex flex-col gap-0.5 p-2">
          {visibleNavSections.map((section) => (
            <NavLink
              key={section.to}
              to={section.to}
              end={section.to === "/"}
              className={({ isActive }) =>
                `rounded-md px-3 py-2 text-sm transition flex items-center justify-between ${
                  isActive
                    ? "bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900"
                    : "text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-900"
                }`
              }
            >
              <span>{section.label}</span>
              {section.label === "HR & Payroll" && pendingCount > 0 && (
                <span className="rounded-full bg-amber-500 px-2 py-0.5 text-[10px] font-bold text-white dark:bg-amber-600">
                  {pendingCount}
                </span>
              )}
            </NavLink>
          ))}
        </nav>
      </aside>

      <div className="flex flex-1 flex-col">
        <header className="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-3 dark:border-slate-800 dark:bg-slate-950">
          <div />
          <div className="flex items-center gap-4 text-sm">
            {user && (
              <NavLink
                to="/profile"
                className={({ isActive }) =>
                  `flex items-center gap-1.5 font-medium transition hover:text-indigo-600 ${
                    isActive ? "text-indigo-600" : "text-slate-500 dark:text-slate-400"
                  }`
                }
              >
                <span>👤 Profile (User #{user.userId})</span>
              </NavLink>
            )}
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

      {/* Mandatory Full-Screen Alert Overlay */}
      {activeAlert && (
        <div className="fixed inset-0 z-[99999] flex items-center justify-center bg-red-950/80 backdrop-blur-sm p-4">
          <div className="w-full max-w-2xl rounded-2xl bg-white shadow-2xl dark:bg-slate-900 border-2 border-red-500 overflow-hidden animate-slide-in">
            <div className="bg-red-500 px-6 py-4 flex items-center gap-3 text-white">
              <span className="text-2xl">🚨</span>
              <h2 className="text-xl font-black uppercase tracking-widest text-white">Critical Alert</h2>
            </div>
            <div className="p-8">
              <h3 className="text-2xl font-bold text-slate-900 dark:text-white mb-4">{activeAlert.title}</h3>
              <p className="text-lg text-slate-700 dark:text-slate-300 whitespace-pre-wrap">{activeAlert.message}</p>
              
              <div className="mt-10 flex justify-end">
                <button
                  onClick={() => handleMarkRead(activeAlert.communication_id)}
                  className="rounded-lg bg-red-600 px-8 py-3 font-bold text-white shadow-lg hover:bg-red-700 hover:scale-105 transition-all"
                >
                  I Acknowledge & Understand
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Real-time Slide-in Toast Notification for regular notices */}
      {!activeAlert && activeToast && (
        <div className="fixed bottom-5 left-5 right-5 sm:left-auto z-[9999] flex max-w-full sm:w-96 flex-col rounded-2xl bg-slate-900 p-5 text-white shadow-2xl transition-all duration-300 animate-slide-in border border-slate-700 dark:bg-slate-950">
          <div className="flex items-start justify-between mb-2">
            <div className="flex items-center gap-2">
              <span className="text-lg">📢</span>
              <h5 className="text-sm font-bold text-indigo-400">{activeToast.title}</h5>
            </div>
            <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 uppercase tracking-wider">{activeToast.type}</span>
          </div>
          <p className="text-xs text-slate-300 line-clamp-3 mb-4">{activeToast.message}</p>
          <div className="flex items-center justify-between mt-auto">
            <span className="text-[10px] text-slate-500">{unreadComms.length - 1 > 0 ? `+${unreadComms.length - 1} more unread` : 'New Notification'}</span>
            <button
              onClick={() => handleMarkRead(activeToast.communication_id)}
              className="rounded bg-indigo-600 px-4 py-1.5 text-xs font-bold text-white hover:bg-indigo-500 transition shadow-sm"
            >
              Mark as Read
            </button>
          </div>
        </div>
      )}

      {/* Legacy System Toast (if any) */}
      {toast && !activeAlert && !activeToast && (
        <div className="fixed bottom-5 left-5 right-5 sm:left-auto z-[9999] flex max-w-full sm:w-80 items-center justify-between gap-3 rounded-2xl bg-slate-900 p-4 text-white shadow-2xl transition-all duration-300 animate-slide-in border border-slate-700 dark:bg-slate-950">
          <div className="flex-1">
            <h5 className="text-xs font-bold text-amber-400">{toast.title}</h5>
            <p className="text-[11px] text-slate-300 mt-0.5">{toast.desc}</p>
          </div>
          <div className="flex items-center gap-1.5 shrink-0">
            <button
              onClick={() => {
                navigate("/hr-payroll?tab=leave_management&sub=requests");
                setToast(null);
              }}
              className="rounded bg-amber-500 px-2 py-1 text-[10px] font-bold text-slate-950 hover:bg-amber-400 transition"
            >
              Review
            </button>
            <button
              onClick={() => setToast(null)}
              className="text-slate-400 hover:text-white text-xs font-bold px-1"
            >
              ✕
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
