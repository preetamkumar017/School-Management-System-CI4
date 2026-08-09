import { useState, useEffect } from "react";
import { api, apiErrorMessage } from "../../lib/api";
import { inputClass } from "../../components/ui/form";

interface AttendanceRecord {
  staff_attendance_id: number;
  employee_id: number;
  attendance_date: string;
  state: "Present" | "On Leave" | "Unauthorized" | "Half Day" | "Missing Punch";
  first_in_time: string | null;
  last_out_time: string | null;
  total_hours: number;
  late_minutes: number;
  early_minutes: number;
  overtime_hours: number;
  is_half_day: boolean;
}

export default function DailyAttendancePage() {
  const [date, setDate] = useState(new Date().toISOString().split("T")[0]);
  const [records, setRecords] = useState<AttendanceRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    fetchDailyAttendance(date);
  }, [date]);

  const fetchDailyAttendance = async (d: string) => {
    try {
      setLoading(true);
      setError("");
      const res = await api.get<{ data: AttendanceRecord[] }>(`/attendance/staff-attendance/daily?date=${d}`);
      setRecords(res.data.data);
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setLoading(false);
    }
  };

  const getStatusBadge = (state: string) => {
    switch (state) {
      case "Present": return <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">Present</span>;
      case "On Leave": return <span className="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">On Leave</span>;
      case "Unauthorized": return <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800 dark:bg-red-900/30 dark:text-red-400">Absent</span>;
      case "Half Day": return <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">Half Day</span>;
      case "Missing Punch": return <span className="rounded-full bg-orange-100 px-2 py-0.5 text-xs font-semibold text-orange-800 dark:bg-orange-900/30 dark:text-orange-400">Missing Punch</span>;
      default: return <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-800 dark:bg-slate-800 dark:text-slate-300">{state}</span>;
    }
  };

  const formatTime = (timeStr: string | null) => {
    if (!timeStr) return "-";
    // Extracts "HH:MM" from "YYYY-MM-DD HH:MM:SS"
    const parts = timeStr.split(" ");
    if (parts.length === 2) {
      const timeParts = parts[1].split(":");
      return `${timeParts[0]}:${timeParts[1]}`;
    }
    return timeStr;
  };

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
      <div className="mb-6 flex items-end justify-between">
        <div>
          <h2 className="text-lg font-bold text-slate-900 dark:text-white">Daily Attendance</h2>
          <p className="text-sm text-slate-500 dark:text-slate-400">View detailed punches, overtime, and late marks.</p>
        </div>
        <div className="flex items-center gap-3">
          <label className="text-sm font-semibold text-slate-700 dark:text-slate-300">Date:</label>
          <input
            type="date"
            value={date}
            onChange={(e) => setDate(e.target.value)}
            className={inputClass}
          />
        </div>
      </div>

      {error && (
        <div className="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-600 dark:bg-red-900/30 dark:text-red-400">
          {error}
        </div>
      )}

      {loading ? (
        <p className="text-sm text-slate-500">Loading daily attendance...</p>
      ) : records.length === 0 ? (
        <p className="text-sm text-slate-500">No attendance records found for this date.</p>
      ) : (
        <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
          <table className="w-full text-left text-sm">
            <thead className="bg-slate-50 text-xs text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
              <tr>
                <th className="px-4 py-3 font-semibold">Employee ID</th>
                <th className="px-4 py-3 font-semibold">First In</th>
                <th className="px-4 py-3 font-semibold">Last Out</th>
                <th className="px-4 py-3 font-semibold">Total Hrs</th>
                <th className="px-4 py-3 font-semibold">Late/Early</th>
                <th className="px-4 py-3 font-semibold">Overtime</th>
                <th className="px-4 py-3 font-semibold">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {records.map((r) => (
                <tr key={r.staff_attendance_id} className="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                  <td className="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">
                    EMP-{r.employee_id.toString().padStart(4, "0")}
                  </td>
                  <td className="px-4 py-3 text-slate-600 dark:text-slate-300">{formatTime(r.first_in_time)}</td>
                  <td className="px-4 py-3 text-slate-600 dark:text-slate-300">{formatTime(r.last_out_time)}</td>
                  <td className="px-4 py-3 text-slate-600 dark:text-slate-300">{r.total_hours}</td>
                  <td className="px-4 py-3">
                    {r.late_minutes > 0 && <span className="text-red-600 dark:text-red-400 block text-xs">+{r.late_minutes}m Late</span>}
                    {r.early_minutes > 0 && <span className="text-orange-600 dark:text-orange-400 block text-xs">-{r.early_minutes}m Early</span>}
                    {r.late_minutes === 0 && r.early_minutes === 0 && <span className="text-slate-400">-</span>}
                  </td>
                  <td className="px-4 py-3">
                    {r.overtime_hours > 0 ? (
                      <span className="font-semibold text-indigo-600 dark:text-indigo-400">{r.overtime_hours}h</span>
                    ) : (
                      <span className="text-slate-400">-</span>
                    )}
                  </td>
                  <td className="px-4 py-3">{getStatusBadge(r.state)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
