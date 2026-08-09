import { useState, useEffect } from "react";
import { api, apiErrorMessage } from "../../../lib/api";
import TeacherWorkloadDetails from "./TeacherWorkloadDetails";

interface WorkloadReport {
  employee_id: number;
  first_name: string;
  last_name: string;
  department: string;
  total_periods: number;
  regular_classes: number;
  extra_classes: number;
  substitutions: number;
  status: "Optimal" | "Overloaded" | "Under-utilized";
}

export default function TeacherWorkloadDashboard() {
  const [reports, setReports] = useState<WorkloadReport[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [selectedTeacherId, setSelectedTeacherId] = useState<number | null>(null);

  useEffect(() => {
    fetchWorkload();
  }, []);

  const fetchWorkload = async () => {
    try {
      setLoading(true);
      setError("");
      const res = await api.get<{ data: WorkloadReport[] }>("/timetable/workload/teachers");
      setReports(res.data.data);
    } catch (err) {
      setError(apiErrorMessage(err));
    } finally {
      setLoading(false);
    }
  };

  if (selectedTeacherId) {
    return (
      <TeacherWorkloadDetails
        employeeId={selectedTeacherId}
        onBack={() => setSelectedTeacherId(null)}
      />
    );
  }

  const getStatusBadge = (status: string) => {
    if (status === "Optimal") return <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">Optimal</span>;
    if (status === "Overloaded") return <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800 dark:bg-red-900/30 dark:text-red-400">Overloaded</span>;
    if (status === "Under-utilized") return <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">Under-utilized</span>;
    return <span>{status}</span>;
  };

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
      <div className="mb-6 flex items-end justify-between">
        <div>
          <h2 className="text-lg font-bold text-slate-900 dark:text-white">Teacher Workload Dashboard</h2>
          <p className="text-sm text-slate-500 dark:text-slate-400">Monitor periods assigned, extra classes, and free time for teaching staff.</p>
        </div>
      </div>

      {error && <div className="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-600 dark:bg-red-900/30 dark:text-red-400">{error}</div>}

      {loading ? (
        <p className="text-sm text-slate-500">Loading workload data...</p>
      ) : reports.length === 0 ? (
        <p className="text-sm text-slate-500">No teaching staff records found.</p>
      ) : (
        <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
          <table className="w-full text-left text-sm">
            <thead className="bg-slate-50 text-xs text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
              <tr>
                <th className="px-4 py-3 font-semibold">Teacher</th>
                <th className="px-4 py-3 font-semibold">Department</th>
                <th className="px-4 py-3 font-semibold">Total Periods/Week</th>
                <th className="px-4 py-3 font-semibold">Extra Classes</th>
                <th className="px-4 py-3 font-semibold">Substitutions</th>
                <th className="px-4 py-3 font-semibold">Status</th>
                <th className="px-4 py-3 font-semibold text-right">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {reports.map((r) => (
                <tr key={r.employee_id} className="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                  <td className="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">
                    {r.first_name} {r.last_name}
                  </td>
                  <td className="px-4 py-3 text-slate-600 dark:text-slate-300">{r.department}</td>
                  <td className="px-4 py-3 text-slate-600 dark:text-slate-300 font-bold">{r.total_periods}</td>
                  <td className="px-4 py-3 text-indigo-600 dark:text-indigo-400">{r.extra_classes}</td>
                  <td className="px-4 py-3 text-amber-600 dark:text-amber-400">{r.substitutions}</td>
                  <td className="px-4 py-3">{getStatusBadge(r.status)}</td>
                  <td className="px-4 py-3 text-right">
                    <button
                      onClick={() => setSelectedTeacherId(r.employee_id)}
                      className="text-sm font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
                    >
                      View Details →
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
